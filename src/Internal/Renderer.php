<?php

declare(strict_types=1);

namespace ErrorExplainer\Internal;

use ErrorExplainer\Config;
use ErrorExplainer\Contracts\RendererInterface;

use function array_slice;
use function count;
use function dirname;
use function function_exists;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;

use const ENT_QUOTES;
use const EXTR_SKIP;
use const FILE_IGNORE_NEW_LINES;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;
use const PHP_SAPI;
use const STDOUT;
use const STR_PAD_LEFT;

final class Renderer implements RendererInterface
{
    /**
     * @param array<string,mixed> $explanation
     */
    public function render(array $explanation, Config $config, string $kind, bool $isShutdown): void
    {
        $format = $config->output;
        if (Config::OUTPUT_AUTO === $format) {
            $format = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') ? Config::OUTPUT_TEXT : Config::OUTPUT_HTML;
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

        // Summary
        if ('' !== $data['summary']) {
            $lines[] = $section(Translator::t($config, 'labels.summary')) . ' ' . $data['summary'];
        }

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
            // Safe minimal fallback if template is missing
            $esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            echo '<!DOCTYPE html><html lang="' . $esc($data['docLang']) . '"><head><meta charset="utf-8"><title>' . $esc($data['title']) . '</title></head><body style="font-family:system-ui;padding:24px">';
            echo '<h1 style="margin:0 0 4px 0">' . $esc($data['title']) . '</h1>';
            if (!empty($data['subtitle'])) {
                echo '<div style="color:#6b7280;font-size:12px;margin:0 0 8px 0">' . $esc($data['subtitle']) . '</div>';
            }

            echo '<div style="color:#6b7280;font-size:12px">' . $esc($data['severity']) . '</div>';
            if ('' !== $data['where']) {
                echo '<div style="margin:8px 0;font-family:monospace">' . $esc($data['where']) . '</div>';
            }

            if ('' !== $data['summary']) {
                echo '<p>' . $esc($data['summary']) . '</p>';
            }

            echo '</body></html>';

            return;
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

        $summary = (string) ($explanation['summary'] ?? '');
        $details = (string) ($explanation['details'] ?? '');
        $suggestions = isset($explanation['suggestions']) && is_array($explanation['suggestions']) ? $explanation['suggestions'] : [];

        $trace = isset($explanation['trace']) && is_array($explanation['trace']) ? $explanation['trace'] : [];
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
            $frames[] = [
                'idx' => $idx,
                'sig' => $sig,
                'loc' => $loc,
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
            ],
            'labels' => [
                'code' => Translator::t($config, 'html.labels.code'),
                'get' => Translator::t($config, 'html.labels.get'),
                'post' => Translator::t($config, 'html.labels.post'),
                'cookie' => Translator::t($config, 'html.labels.cookie'),
                'session' => Translator::t($config, 'html.labels.session'),
            ],
        ];

        // Attach full state to each frame and prepend origin as frame #0 (so it's part of the stack)
        $framesOut = [];
        if ('' !== $where) {
            $framesOut[] = [
                'idx' => 0,
                'sig' => '(origin)',
                'loc' => $where,
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
            'summary' => $summary,
            'verbose' => $config->verbose,
            'details' => $details,
            'suggestions' => $suggestions,
            'frames' => $framesOut,
            'labels' => $labels,
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

            // Line already has <?php, highlight as-is
            $highlightedCode = @highlight_string($codeLine, true);
            if (is_string($highlightedCode)) {
                $highlightedCode = preg_replace('#^<code><span[^>]*>(.*)</span></code>$#s', '$1', $highlightedCode) ?? $highlightedCode;
                $highlightedCode = trim($highlightedCode);
            } else {
                $highlightedCode = htmlspecialchars($codeLine, ENT_QUOTES, 'UTF-8');
            }

            $rowClass = $ln === $line ? ' class="error-line"' : '';
            $output .= '<tr' . $rowClass . '>';
            $output .= '<td class="line-number">' . htmlspecialchars($lineNum, ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td class="code-content">' . $highlightedCode . '</td>';
            $output .= '</tr>';
        }

        $output .= '</table></div>';

        return $output;
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
