<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\RendererInterface;
use RuntimeException;

use function count;
use function dirname;
use function function_exists;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;

use const DIRECTORY_SEPARATOR;
use const ENT_QUOTES;
use const EXTR_SKIP;
use const FILE_IGNORE_NEW_LINES;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;
use const PHP_SAPI;
use const STDOUT;
use const STR_PAD_LEFT;

/**
 * Renders error explanations across multiple formats (HTML, text, JSON).
 *
 * Why separate rendering:
 * - Presentation concerns change more frequently than domain logic; isolating them reduces ripple effects.
 * - Different environments (CLI vs web) require different defaults and headers.
 */
final class Renderer implements RendererInterface
{
    /**
     * Decide output format at runtime and delegate to specialized renderers.
     *
     * Why AUTO: we prefer text in CLI for readability and HTML in web for rich context.
     *
     * Also: if the incoming HTTP request declares a JSON content type (e.g. application/json,
     * application/ld+json, application/json-patch+json), we force JSON output regardless of config
     * so API clients always get machine-readable errors.
     *
     * @param array<string,mixed> $explanation
     */
    public function render(array $explanation, Config $config, string $kind, bool $isShutdown): void
    {
        $format = $config->output;

        // Force JSON output for JSON HTTP content types
        if (Config::OUTPUT_AUTO === $format) {
            $format = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') ? Config::OUTPUT_TEXT : Config::OUTPUT_HTML;
            if ($this->isHttpJsonRequest()) {
                $format = Config::OUTPUT_JSON;
            }
        }

        if (Config::OUTPUT_JSON === $format) {
            $this->renderJson($explanation);

            return;
        }

        if (Config::OUTPUT_TEXT === $format) {
            $this->renderText($explanation, $config);

            return;
        }

        // default to HTML
        $this->renderHtml($explanation, $config);
    }

    /**
     * Emit a machine-readable representation for integrations.
     *
     * Why set headers here: when running under a web SAPI we owe a proper content-type and a 500 status code
     * so upstream reverse proxies and clients can react appropriately.
     */
    /**
     * Detect whether the incoming HTTP request declares a JSON content type.
     */
    private function isHttpJsonRequest(): bool
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return false;
        }

        $contentType = '';
        $accept = '';

        // Prefer getallheaders() when available (Apache/FPM/cli-server)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $name => $value) {
                if (!is_string($name) || !is_string($value)) {
                    continue;
                }

                $lname = strtolower($name);
                if ('content-type' === $lname) {
                    $contentType = $value;
                } elseif ('accept' === $lname) {
                    $accept = $value;
                }
            }
        }

        if ('' === $contentType) {
            $ct = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
            $contentType = is_string($ct) ? $ct : '';
        }

        if ('' === $accept) {
            $ac = $_SERVER['HTTP_ACCEPT'] ?? '';
            $accept = is_string($ac) ? $ac : '';
        }

        $ct = strtolower(trim($contentType));
        $ac = strtolower(trim($accept));

        // If either Content-Type or Accept declares JSON (including +json or json-p), force JSON output.
        if ('' !== $ct && str_contains($ct, 'json')) {
            return true;
        }

        return '' !== $ac && str_contains($ac, 'json');
    }

    /**
     * @param array<string,mixed> $explanation
     */
    private function renderJson(array $explanation): void
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }

        echo json_encode($explanation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), (PHP_SAPI === 'cli' ? "\n" : '');
    }

    /**
     * @param array<string,mixed> $explanation
     */
    private function renderText(array $explanation, Config $config): void
    {
        $data = $this->buildViewData($explanation, $config);
        $lines = [];

        $ansi = $this->supportsAnsi();
        $muted = static fn (string $s): string => $ansi ? "\033[90m{$s}\033[0m" : $s;
        $bold = static fn (string $s): string => $ansi ? "\033[1m{$s}\033[0m" : $s;
        $titleC = static fn (string $s): string => $ansi ? "\033[1;31m{$s}\033[0m" : $s; // bold red
        $section = static fn (string $s): string => $ansi ? "\033[1;34m{$s}\033[0m" : $s; // bold blue
        $bullet = static fn (string $s): string => $ansi ? "\033[36m{$s}\033[0m" : $s; // cyan
        $fileC = static fn (string $s): string => $ansi ? "\033[33m{$s}\033[0m" : $s; // yellow

        // Header
        $header = '🚨 ' . $data['title'];
        if ('' !== $data['where']) {
            $header .= ' ' . Translator::t($config, 'labels.in') . ' ' . $data['where'];
        }

        $lines[] = $titleC($header);
        $lines[] = $muted((string) $data['severity']);

        // Stack (show top frames with code excerpt)
        if (!empty($data['frames'])) {
            $lines[] = '';
            $lines[] = $section((string) ($data['labels']['headings']['stack'] ?? 'Stack trace'));
            $maxFrames = 3; // keep concise
            $i = 0;
            foreach ($data['frames'] as $f) {
                $sig = (string) ($f['sig'] ?? '');
                $loc = (string) ($f['loc'] ?? '');
                $idx = (int) ($f['idx'] ?? 0);
                $locOut = '' !== $loc ? $fileC($loc) : '';
                $lines[] = sprintf('#%d %s %s%s', $idx, '' !== $sig ? $bold($sig) : '(unknown)', '' !== $locOut ? '— ' : '', $locOut);

                if ($i < $maxFrames && '' !== $loc) {
                    // derive file and line from loc "path:line"
                    $file = $loc;
                    $line = null;
                    $pos = strrpos($loc, ':');
                    if (false !== $pos) {
                        $file = substr($loc, 0, $pos);
                        $lineStr = substr($loc, $pos + 1);
                        $line = ctype_digit($lineStr) ? (int) $lineStr : null;
                    }

                    $excerpt = $this->renderCodeExcerptText($file, $line ?? 0, 3);
                    if ('' !== $excerpt) {
                        foreach (explode("\n", $excerpt) as $l) {
                            $lines[] = '   ' . $l;
                        }
                    }
                }

                ++$i;
            }
        }

        // Details (only when verbose)
        if (!empty($data['details']) && (bool) $data['verbose']) {
            $lines[] = $section(Translator::t($config, 'labels.details'));
            $lines[] = (string) $data['details'];
        }

        // Suggestions
        if (!empty($data['suggestions'])) {
            $lines[] = $section(Translator::t($config, 'labels.suggestions'));
            foreach ($data['suggestions'] as $s) {
                $lines[] = ' ' . $bullet('•') . ' ' . $s;
            }
        }

        echo implode("\n", $lines), "\n";
    }

    /**
     * @param array<string,mixed> $explanation
     */
    private function renderHtml(array $explanation, Config $config): void
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' && !headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            http_response_code(500);
        }

        $template = $config->template ?? (getenv('PHP_ERROR_INSIGHT_TEMPLATE') ?: null);
        if ('' === $template || '0' === $template || null === $template) {
            $template = dirname(__DIR__, 2) . '/resources/views/error.php';
        }

        $data = $this->buildViewData($explanation, $config);

        if (!is_file($template)) {
            throw new RuntimeException(sprintf('Template file "%s" not found', $template));
        }

        // Isolated scope include, expose $data keys as variables to the template
        (static function (string $__template, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__template;
        })($template, $data);
    }

    /**
     * @param array<string, mixed> $explanation
     *
     * @return array<string, mixed>
     */
    private function buildViewData(array $explanation, Config $config): array
    {
        $docLang = '' !== $config->language && '0' !== $config->language ? $config->language : 'it';

        $original = isset($explanation['original']) && is_array($explanation['original']) ? $explanation['original'] : [];
        $origMessage = isset($original['message']) ? (string) $original['message'] : '';
        $file = isset($original['file']) ? (string) $original['file'] : '';
        $line = isset($original['line']) ? (string) $original['line'] : '';
        $where = trim($file . ('' !== $line ? ":{$line}" : ''));

        // Title must be the error/warning message (fallback to previous title if missing)
        $title = '' !== $origMessage ? $origMessage : (string) ($explanation['title'] ?? 'PHP Error Explainer');

        // Subtitle shown when AI was used
        $aiTitle = Translator::t($config, 'title.ai');
        $subtitle = ((string) ($explanation['title'] ?? '')) === $aiTitle ? $aiTitle : '';

        $severity = (string) ($explanation['severityLabel'] ?? 'Error');

        $details = (string) ($explanation['details'] ?? '');
        $suggestions = isset($explanation['suggestions']) && is_array($explanation['suggestions']) ? $explanation['suggestions'] : [];

        $trace = isset($explanation['trace']) && is_array($explanation['trace']) ? $explanation['trace'] : [];

        // Compute project root and editor URL template
        $projectRoot = null !== $config->projectRoot && '' !== $config->projectRoot && '0' !== $config->projectRoot ? $config->projectRoot : (getenv('PHP_ERROR_INSIGHT_ROOT') ?: getcwd());
        $projectRoot = (string) $projectRoot;
        if ('' !== $projectRoot) {
            $rp = realpath($projectRoot);
            $projectRoot = false !== $rp ? rtrim($rp, DIRECTORY_SEPARATOR) : rtrim($projectRoot, DIRECTORY_SEPARATOR);
        }

        $editorTpl = null !== $config->editorUrl && '' !== $config->editorUrl && '0' !== $config->editorUrl ? $config->editorUrl : (getenv('PHP_ERROR_INSIGHT_EDITOR') ?: '');

        $normalize = static function (string $p): string {
            $p = str_replace(['\\'], '/', $p);

            // collapse duplicate slashes
            return preg_replace('#/{2,}#', '/', $p) ?? $p;
        };
        $hostProjectRoot = null !== $config->hostProjectRoot && '' !== $config->hostProjectRoot && '0' !== $config->hostProjectRoot ? $config->hostProjectRoot : (getenv('PHP_ERROR_INSIGHT_HOST_ROOT') ?: '');
        $hostProjectRoot = (string) $hostProjectRoot;
        if ('' !== $hostProjectRoot) {
            $hostProjectRoot = rtrim('' !== (string) realpath($hostProjectRoot) && '0' !== (string) realpath($hostProjectRoot) ? (string) realpath($hostProjectRoot) : $hostProjectRoot, DIRECTORY_SEPARATOR);
        }

        $toRel = static function (?string $abs) use ($projectRoot, $normalize): string {
            if (null === $abs || '' === $abs) {
                return '';
            }

            $a = $normalize($abs);
            $root = $normalize($projectRoot);
            if ('' !== $root && str_starts_with($a, $root . '/')) {
                $rel = substr($a, strlen($root) + 1);
            } else {
                // try vendor truncation if path contains "/vendor/"
                $pos = strpos($a, '/vendor/');
                $rel = false !== $pos ? substr($a, $pos + 1) : ltrim($a, '/');
            }

            return $rel;
        };
        $toEditorHref = static function (string $tpl, ?string $abs, ?int $ln) use ($normalize, $projectRoot, $hostProjectRoot): string {
            if ('' === $tpl || null === $abs || '' === $abs || null === $ln || 0 === $ln) {
                return '';
            }

            $file = $normalize($abs);
            $pr = $normalize($projectRoot);
            $hr = $normalize($hostProjectRoot);
            if ('' !== $hr && '' !== $pr && str_starts_with($file, $pr . '/')) {
                $file = $hr . substr($file, strlen($pr));
            }

            $search = ['%file', '%line'];
            $replace = [$file, (string) $ln];

            return str_replace($search, $replace, $tpl);
        };

        $frames = [];
        $idx = 0;
        foreach ($trace as $frame) {
            $fn = isset($frame['function']) ? (string) $frame['function'] : 'unknown';
            $cls = isset($frame['class']) ? (string) $frame['class'] : '';
            $type = isset($frame['type']) ? (string) $frame['type'] : '';
            $ff = isset($frame['file']) ? (string) $frame['file'] : '';
            $ll = isset($frame['line']) ? (int) $frame['line'] : 0;
            $sig = trim($cls . $type . $fn . '()');
            $loc = '' !== $ff ? ($ff . (0 !== $ll ? ':' . $ll : '')) : '';
            $args = isset($frame['args']) && is_array($frame['args']) ? $frame['args'] : [];
            $frames[] = [
                'idx' => $idx,
                'sig' => $sig,
                'loc' => $loc,
                'file' => $ff,
                'line' => $ll,
                'args' => $args,
                'rel' => $toRel($ff),
                'editorHref' => $toEditorHref((string) $editorTpl, $ff, $ll),
                'codeHtml' => $this->renderCodeExcerpt($ff, $ll),
            ];
            ++$idx;
        }

        $labels = [
            'headings' => [
                'details' => Translator::t($config, 'html.headings.details'),
                'env_details' => Translator::t($config, 'html.headings.env_details'),
                'suggestions' => Translator::t($config, 'html.headings.suggestions'),
                'stack' => Translator::t($config, 'html.headings.stack'),
                'info' => Translator::t($config, 'html.headings.info'),
            ],
            'labels' => [
                'code' => Translator::t($config, 'html.labels.code'),
                'get' => Translator::t($config, 'html.labels.get'),
                'post' => Translator::t($config, 'html.labels.post'),
                'cookie' => Translator::t($config, 'html.labels.cookie'),
                'session' => Translator::t($config, 'html.labels.session'),
                'language' => Translator::t($config, 'html.labels.language'),
                'ai_model' => Translator::t($config, 'html.labels.ai_model'),
                'editor_url' => Translator::t($config, 'html.labels.editor_url'),
                'verbose' => Translator::t($config, 'html.labels.verbose'),
            ],
            'toolbar' => [
                'copy_title' => Translator::t($config, 'html.toolbar.copy_title'),
                'copy_stack' => Translator::t($config, 'html.toolbar.copy_stack'),
                'open_in_editor' => Translator::t($config, 'html.toolbar.open_in_editor'),
                'theme' => Translator::t($config, 'html.toolbar.theme'),
            ],
            'stack' => [
                'expand_all' => Translator::t($config, 'html.stack.expand_all'),
                'collapse_all' => Translator::t($config, 'html.stack.collapse_all'),
                'copy' => Translator::t($config, 'html.stack.copy'),
                'open' => Translator::t($config, 'html.stack.open'),
            ],
            'tabs' => [
                'server_request' => Translator::t($config, 'html.tabs.server_request'),
                'env_vars' => Translator::t($config, 'html.tabs.env_vars'),
                'cookies' => Translator::t($config, 'html.tabs.cookies'),
                'session' => Translator::t($config, 'html.tabs.session'),
                'get' => Translator::t($config, 'html.tabs.get'),
                'post' => Translator::t($config, 'html.tabs.post'),
                'files' => Translator::t($config, 'html.tabs.files'),
            ],
            'messages' => [
                'no_excerpt' => Translator::t($config, 'html.messages.no_excerpt'),
                'rendered_by' => Translator::t($config, 'html.footer.rendered_by'),
            ],
            'aria' => [
                'page_actions' => Translator::t($config, 'html.aria.page_actions'),
                'toggle_theme' => Translator::t($config, 'html.aria.toggle_theme'),
                'stack_actions' => Translator::t($config, 'html.aria.stack_actions'),
                'copy_line' => Translator::t($config, 'html.aria.copy_line'),
                'code_excerpt' => Translator::t($config, 'html.aria.code_excerpt'),
                'server_dump' => Translator::t($config, 'html.aria.server_dump'),
                'env_tabs' => Translator::t($config, 'html.aria.env_tabs'),
                'frame_args' => Translator::t($config, 'html.aria.frame_args'),
            ],
            'js' => [
                'copied' => Translator::t($config, 'html.js.copied'),
                'title_copied' => Translator::t($config, 'html.js.title_copied'),
                'stack_copied' => Translator::t($config, 'html.js.stack_copied'),
            ],
            'badge' => [
                'severity' => Translator::t($config, 'html.badge.severity'),
            ],
        ];

        // Attach full state to each frame and prepend origin as frame #0 (so it's part of the stack)
        $framesOut = [];
        if ('' !== $where) {
            $ff = $file;
            $ll = (int) $line;
            $framesOut[] = [
                'idx' => 0,
                'sig' => '(origin)',
                'loc' => $where,
                'file' => $ff,
                'line' => $ll,
                'args' => [],
                'rel' => $toRel($ff),
                'editorHref' => $toEditorHref((string) $editorTpl, $ff, $ll),
                'codeHtml' => $this->renderCodeExcerpt($file, (int) $line),
            ];
        }

        $startIdx = count($framesOut);
        foreach ($frames as $f) {
            // ensure numeric idx and shift by startIdx
            $f['idx'] += $startIdx;
            $framesOut[] = $f;
        }

        return [
            'docLang' => $docLang,
            'title' => $title,
            'subtitle' => $subtitle,
            'severity' => $severity,
            'where' => $where,
            'verbose' => $config->verbose,
            'details' => $details,
            'suggestions' => $suggestions,
            'frames' => $framesOut,
            'labels' => $labels,
            'projectRoot' => $projectRoot,
            'editorUrl' => (string) $config->editorUrl,
            'aiModel' => (string) $config->model,
        ];
    }

    private function renderCodeExcerpt(?string $file, ?int $line, int $radius = 5): string
    {
        if (null === $file || '' === $file || '0' === $file || (null === $line || 0 === $line) || !is_file($file) || $line < 1) {
            return '';
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if (false === $lines) {
            return '';
        }

        $total = count($lines);
        $start = max(1, $line - $radius);
        $end = min($total, $line + $radius);
        $numWidth = strlen((string) $end);

        $output = '<div class="code-excerpt"><table class="code-table">';

        for ($ln = $start; $ln <= $end; ++$ln) {
            $codeLine = $lines[$ln - 1];
            $lineNum = str_pad((string) $ln, $numWidth, ' ', STR_PAD_LEFT);

            $highlightedCode = $this->highlightText($codeLine);

            $rowClass = $ln === $line ? ' class="error-line"' : '';
            $output .= '<tr' . $rowClass . '>';
            $output .= '<td class="line-number">' . htmlspecialchars($lineNum, ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td class="code-content">' . $highlightedCode . '</td>';
            $output .= '</tr>';
        }

        return $output . '</table></div>';
    }

    private function highlightText(string $text): string
    {
        $text = highlight_string($text, true);

        return preg_replace('|<code style="color: #[a-fA-F0-9]{6}">|', '<code>', $text);
    }

    private function renderCodeExcerptText(?string $file, ?int $line, int $radius = 5): string
    {
        if (null === $file || '' === $file || '0' === $file || (null === $line || 0 === $line) || !is_file($file) || $line < 1) {
            return '';
        }

        $rows = @file($file, FILE_IGNORE_NEW_LINES);
        if (false === $rows) {
            return '';
        }

        $ansi = $this->supportsAnsi();
        $hl = static fn (string $s): string => $ansi ? "\033[1;37;41m{$s}\033[0m" : $s; // white on red for current line number gutter
        $dim = static fn (string $s): string => $ansi ? "\033[90m{$s}\033[0m" : $s;
        $total = count($rows);
        $start = max(1, $line - $radius);
        $end = min($total, $line + $radius);
        $numWidth = strlen((string) $end);
        $out = [];
        for ($ln = $start; $ln <= $end; ++$ln) {
            $prefix = $ln === $line ? '>' : ' ';
            $num = str_pad((string) $ln, $numWidth, ' ', STR_PAD_LEFT);
            $gutter = $ln === $line ? $hl($num) : $dim($num);
            $code = $rows[$ln - 1];
            // show tabs as spaces
            $code = str_replace("\t", '    ', $code);
            $out[] = sprintf('%s %s | %s', $prefix, $gutter, $code);
        }

        return implode("\n", $out);
    }

    private function supportsAnsi(): bool
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            return false;
        }

        if (getenv('NO_COLOR')) {
            return false;
        }

        $force = getenv('FORCE_COLOR');
        if ($force && in_array(strtolower((string) $force), ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        // Check TTY if possible
        if (function_exists('stream_isatty')) {
            /* @noinspection PhpComposerExtensionStubsInspection */
            return @stream_isatty(STDOUT);
        }

        if (function_exists('posix_isatty')) {
            /* @noinspection PhpComposerExtensionStubsInspection */
            return @posix_isatty(STDOUT);
        }

        // Fall back to TERM
        $term = getenv('TERM');

        return is_string($term) && 'dumb' !== strtolower($term);
    }
}
