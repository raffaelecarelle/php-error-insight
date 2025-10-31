<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\RendererInterface;
use PhpErrorInsight\Internal\Model\Explanation;
use RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Filesystem\Path;

use function count;
use function function_exists;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;

use const DIRECTORY_SEPARATOR;
use const ENT_QUOTES;
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
     * High-level renderer: delegates all PHP core function calls to small util services.
     * Why: improves testability and keeps this class focused on presentation logic.
     */
    public function __construct(
        private readonly Util\EnvUtil $env = new Util\EnvUtil(),
        private readonly Util\HttpUtil $http = new Util\HttpUtil(),
        private readonly Util\JsonUtil $json = new Util\JsonUtil(),
        private readonly Util\StringUtil $str = new Util\StringUtil(),
        private readonly Util\FileUtil $file = new Util\FileUtil(),
        private readonly Util\OutputUtil $out = new Util\OutputUtil(),
        private readonly Util\PathUtil $path = new Util\PathUtil(),
        private readonly Util\HttpClientUtil $httpClientUtil = new Util\HttpClientUtil(),
    ) {
    }

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
            $format = $this->isCliOrPhpdbg() ? Config::OUTPUT_TEXT : Config::OUTPUT_HTML;
            if ($this->httpClientUtil->isHttpJsonRequest()) {
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
     * @param array<string,mixed> $explanation
     */
    private function renderJson(array $explanation): void
    {
        if (!$this->isCliOrPhpdbg() && !$this->http->headersSent()) {
            $this->sendJsonHeaders();
        }

        $json = $this->json->encode($explanation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $suffix = $this->isCliOrPhpdbg() ? "\n" : '';
        $this->out->write($json . $suffix);
    }

    /**
     * @param array<string,mixed> $explanation
     */
    private function renderText(array $explanation, Config $config): void
    {
        $exp = Explanation::fromArray($explanation);

        $formatter = new OutputFormatter($this->supportsAnsi());
        $styler = new ConsoleStyler();
        $styler->registerStyles($formatter, $config->consoleColors ?? null);
        // Register code token styles (Dracula theme by default), allow overrides from config
        $highlighter = new CodeHighlighter();
        $tokenOverrides = null;
        if (is_array($config->consoleColors ?? null) && isset($config->consoleColors['tokens']) && is_array($config->consoleColors['tokens'])) {
            /** @var array<string, array{0:string,1:string|null,2:array<string>}> $tokenOverrides */
            $tokenOverrides = $config->consoleColors['tokens'];
        }

        $highlighter->registerStyles($formatter, CodeHighlighter::THEME_DRACULA, $tokenOverrides);

        $severity = $exp->severityLabel;
        $message = '' !== $exp->message() ? $exp->message() : $exp->title;
        $file = $exp->file();
        $line = $exp->line();
        $suggestions = $exp->suggestions;

        // Severity background for titles (white text on colored background)
        $sevLower = strtolower($severity);
        $sevKey = 'info';
        if (str_contains($sevLower, 'exception') || str_contains($sevLower, 'error') || str_contains($sevLower, 'fatal') || str_contains($sevLower, 'critical')) {
            $sevKey = 'error';
        } elseif (str_contains($sevLower, 'warning')) {
            $sevKey = 'warning';
        }

        $bgName = null;
        if (is_array($config->consoleColors ?? null) && isset($config->consoleColors['severity']) && is_array($config->consoleColors['severity'])) {
            $map = $config->consoleColors['severity'];
            $bg = $map[$sevKey] ?? null;
            if (is_string($bg) && '' !== $bg) {
                $bgName = strtolower($bg);
            }
        }

        if (null === $bgName) {
            $bgName = match ($sevKey) {
                'error' => 'red',
                'warning' => 'yellow',
                default => 'blue',
            };
        }

        $headerOnBg = static function (string $s) use ($styler, $bgName): string {
            return $styler->headerOnBgName($bgName, $s); // bold white on severity background
        };

        // Headers: severity label and message
        echo $formatter->format($headerOnBg(' ' . $severity . ' ')) . "\n\n";
        if ('' !== trim($message)) {
            echo $formatter->format($styler->title($message)) . "\n\n";
        }

        // Suggestions
        if ([] !== $suggestions) {
            echo $formatter->format($styler->suggestion('Suggestions:')) . "\n"; // keep literal for tests
            foreach ($suggestions as $sug) {
                if (!is_string($sug)) {
                    continue;
                }

                echo $formatter->format($styler->suggestion('  - ' . $sug)) . "\n";
            }

            echo "\n";
        }

        // Stack with code excerpt for the first location (original file:line)
        if ('' !== $file && $line > 0) {
            $where = $file . ':' . $line;
            if (null !== $config->projectRoot && '' !== $config->projectRoot && '0' !== $config->projectRoot) {
                $where = Path::makeRelative($where, $config->projectRoot);
            }

            echo $formatter->format('at ' . $styler->location($where)) . "\n";
            $excerpt = $this->renderCodeExcerptText($file, $line, 5);
            if ('' !== $excerpt) {
                // format excerpt tags as well
                echo $formatter->format($excerpt) . "\n\n";
            }
        }

        // Render frames (yellow), without code
        if ([] !== $exp->trace->frames) {
            $i = 1;
            foreach ($exp->trace->frames as $frame) {
                $lineOut = sprintf('%d %s %s', $i, $frame->location(), $frame->signature());
                echo $formatter->format($styler->stack($lineOut)) . "\n";
                ++$i;
            }
        }

        if ($config->verbose) {
            // Footer diagnostic info
            $lang = '' !== $config->language && '0' !== $config->language ? $config->language : 'it';
            $model = (string) $config->model;
            $info = 'lang=' . $lang;
            if ('' !== $model) {
                $info .= ' model=' . $model;
            }

            echo "\n" . $formatter->format($styler->dim($info)) . "\n";
        }

        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            echo "\n";
        }
    }

    /**
     * @param array<string,mixed> $explanation
     */
    private function renderHtml(array $explanation, Config $config): void
    {
        if (!$this->isCliOrPhpdbg() && !$this->http->headersSent()) {
            $this->sendHtmlHeaders();
        }

        $template = $this->getTemplatePath($config);
        $data = $this->buildViewData($explanation, $config);

        if (!$this->file->isFile($template)) {
            throw new RuntimeException($this->str->sprintf('Template file "%s" not found', $template));
        }

        (new Util\TemplateUtil())->includeWithData($template, $data);
    }

    private function isCliOrPhpdbg(): bool
    {
        return $this->env->isCliLike();
    }

    private function sendJsonHeaders(): void
    {
        $this->http->sendHeader('Content-Type: application/json; charset=utf-8');
        $this->http->setResponseCode(500);
    }

    private function sendHtmlHeaders(): void
    {
        $this->http->sendHeader('Content-Type: text/html; charset=utf-8');
        $this->http->setResponseCode(500);
    }

    private function getTemplatePath(Config $config): string
    {
        $template = $config->template ?? (in_array($this->env->getEnv('PHP_ERROR_INSIGHT_TEMPLATE'), ['', '0'], true) ? null : $this->env->getEnv('PHP_ERROR_INSIGHT_TEMPLATE'));
        if ('' === $template || '0' === $template || null === $template) {
            $base = $this->path->dirName(__DIR__, 2);
            $template = $base . '/resources/views/error.php';
        }

        return $template;
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

        $projectRoot = $config->projectRoot !== null && $config->projectRoot !== '' && $config->projectRoot !== '0' ? $config->projectRoot : $this->env->getCwd();

        if ('' !== $projectRoot) {
            $rp = realpath($projectRoot);
            $projectRoot = false !== $rp ? rtrim($rp, DIRECTORY_SEPARATOR) : rtrim($projectRoot, DIRECTORY_SEPARATOR);
        }

        $editorTpl = $config->editorUrl !== null && $config->editorUrl !== '' && $config->editorUrl !== '0' ? $config->editorUrl : $this->env->getEnv('PHP_ERROR_INSIGHT_EDITOR');

        $hostProjectRoot = $config->hostProjectRoot !== null && $config->hostProjectRoot !== '' && $config->hostProjectRoot !== '0' ? $config->hostProjectRoot : $this->env->getEnv('PHP_ERROR_INSIGHT_HOST_ROOT');

        if ('' !== $hostProjectRoot) {
            $hostProjectRoot = rtrim('' !== (string) realpath($hostProjectRoot) && '0' !== (string) realpath($hostProjectRoot) ? (string) realpath($hostProjectRoot) : $hostProjectRoot, DIRECTORY_SEPARATOR);
        }

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
                'rel' => $this->path->makeRelative($ff, $projectRoot),
                'editorHref' => $this->path->toEditorHref($editorTpl, $ff, $ll, $projectRoot, $hostProjectRoot),
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
                'rel' => $this->path->makeRelative($ff, $projectRoot),
                'editorHref' => $this->path->toEditorHref($editorTpl, $ff, $ll, $projectRoot, $hostProjectRoot),
                'codeHtml' => $this->renderCodeExcerpt($ff, $ll),
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
        $clean = preg_replace('|<code style="color: #[a-fA-F0-9]{6}">|', '<code>', $text);

        return is_string($clean) ? $clean : $text;
    }

    private function renderCodeExcerptText(?string $file, ?int $line, int $radius = 5): string
    {
        if ($line < 1 || !is_file($file)) {
            return '';
        }

        $source = @file_get_contents($file);
        if (!is_string($source)) {
            return '';
        }

        // Normalize newlines and visualize tabs as 4 spaces for alignment in terminals
        $source = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", '    '], $source);

        $highlighter = new CodeHighlighter();
        $tokenLines = $highlighter->tokenizeToLines($source);
        if ([] === $tokenLines) {
            return '';
        }

        $styler = new ConsoleStyler();
        $total = count($tokenLines);
        $start = max(1, $line - $radius);
        $end = min($total, $line + $radius);
        $numWidth = strlen((string) $end);

        $out = [];
        for ($ln = $start; $ln <= $end; ++$ln) {
            $prefix = $ln === $line ? $styler->yellow('➜') : ' ';
            $num = str_pad((string) $ln, $numWidth, ' ', STR_PAD_LEFT);
            $gutter = $ln === $line ? $styler->gutterHighlight($num) : $styler->gutterNumber($num);

            // Build colored code for this line from tokens
            $colored = $highlighter->colorTokenLineText($tokenLines[$ln - 1]);

            $sep = $styler->gutterSeparator('|');
            $out[] = sprintf('%s %s %s %s', $prefix, $gutter, $sep, $colored);
        }

        return implode("\n", $out);
    }

    private function supportsAnsi(): bool
    {
        if (!$this->isCliOrPhpdbg()) {
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
