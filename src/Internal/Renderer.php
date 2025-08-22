<?php

declare(strict_types=1);

namespace ErrorExplainer\Internal;

use ErrorExplainer\Config;

final class Renderer
{
    /**
     * @param array<string,mixed> $explanation
     * @param Config $config
     * @param string $kind
     * @param bool $isShutdown
     */
    public function render(array $explanation, Config $config, string $kind, bool $isShutdown): void
    {
        $format = $config->output;
        if ($format === Config::OUTPUT_AUTO) {
            $format = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') ? Config::OUTPUT_TEXT : Config::OUTPUT_HTML;
        }

        if ($format === Config::OUTPUT_JSON) {
            $this->renderJson($explanation);
            return;
        }
        if ($format === Config::OUTPUT_TEXT) {
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
        $lines = [];
        $lines[] = '[PHP Error Explainer] ' . ($explanation['severityLabel'] ?? 'Error');
        $original = isset($explanation['original']) && is_array($explanation['original']) ? $explanation['original'] : [];
        $where = '';
        if (!empty($original)) {
            $file = isset($original['file']) ? (string)$original['file'] : '';
            $line = isset($original['line']) ? (string)$original['line'] : '';
            $where = $file . ($line !== '' ? ":$line" : '');
        }
        if ($where !== '') {
            $lines[] = Translator::t($config, 'labels.in') . ' ' . $where;
        }
        if (!empty($explanation['summary'])) {
            $lines[] = Translator::t($config, 'labels.summary') . ' ' . (string)$explanation['summary'];
        }
        if ($config->verbose && !empty($explanation['details'])) {
            $lines[] = Translator::t($config, 'labels.details');
            $lines[] = (string)$explanation['details'];
        }
        if (!empty($explanation['suggestions']) && is_array($explanation['suggestions'])) {
            $lines[] = Translator::t($config, 'labels.suggestions');
            foreach ($explanation['suggestions'] as $s) {
                $lines[] = ' - ' . (string)$s;
            }
        }
        echo implode("\n", $lines), "\n";
    }

    /**
     * @param array<string,mixed> $explanation
     */
    private function renderHtml(array $explanation, Config $config): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            http_response_code(500);
        }

        $template = $config->template ?? (getenv('ERROR_EXPLAINER_TEMPLATE') ?: null);
        if (!$template) {
            $template = dirname(__DIR__, 2) . '/resources/views/error.php';
        }
        $data = $this->buildViewData($explanation, $config);

        if (!is_file($template)) {
            // Safe minimal fallback if template is missing
            $esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
            echo '<!DOCTYPE html><html lang="' . $esc($data['docLang']) . '"><head><meta charset="utf-8"><title>' . $esc($data['title']) . '</title></head><body style="font-family:system-ui;padding:24px">';
            echo '<h1 style="margin:0 0 8px 0">' . $esc($data['title']) . '</h1>';
            echo '<div style="color:#6b7280;font-size:12px">' . $esc($data['severity']) . '</div>';
            if ($data['where'] !== '') { echo '<div style="margin:8px 0;font-family:monospace">' . $esc($data['where']) . '</div>'; }
            if ($data['summary'] !== '') { echo '<p>' . $esc($data['summary']) . '</p>'; }
            echo '</body></html>';
            return;
        }

        // Isolated scope include, expose $data keys as variables to the template
        (static function (string $__template, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__template;
        })($template, $data);
    }

    private function buildViewData(array $explanation, Config $config): array
    {
        $docLang = $config->language ?: 'it';
        $title = (string)($explanation['title'] ?? 'PHP Error Explainer');
        $severity = (string)($explanation['severityLabel'] ?? 'Error');

        $original = isset($explanation['original']) && is_array($explanation['original']) ? $explanation['original'] : [];
        $file = isset($original['file']) ? (string)$original['file'] : '';
        $line = isset($original['line']) ? (string)$original['line'] : '';
        $where = trim($file . ($line !== '' ? ":$line" : ''));

        $summary = (string)($explanation['summary'] ?? '');
        $details = (string)($explanation['details'] ?? '');
        $suggestions = isset($explanation['suggestions']) && is_array($explanation['suggestions']) ? $explanation['suggestions'] : [];

        $trace = isset($explanation['trace']) && is_array($explanation['trace']) ? $explanation['trace'] : [];
        $frames = [];
        $idx = 0;
        foreach ($trace as $frame) {
            $fn = isset($frame['function']) ? (string)$frame['function'] : 'unknown';
            $cls = isset($frame['class']) ? (string)$frame['class'] : '';
            $type = isset($frame['type']) ? (string)$frame['type'] : '';
            $ff = isset($frame['file']) ? (string)$frame['file'] : '';
            $ll = isset($frame['line']) ? (int)$frame['line'] : 0;
            $sig = trim($cls . $type . $fn . '()');
            $loc = $ff !== '' ? ($ff . ($ll ? ':' . $ll : '')) : '';
            $frames[] = [
                'idx' => $idx,
                'sig' => $sig,
                'loc' => $loc,
                'localsDump' => $this->dumpArgs(isset($frame['locals']) && is_array($frame['locals']) ? $frame['locals'] : []),
                'argsDump' => $this->dumpArgs(isset($frame['args']) && is_array($frame['args']) ? $frame['args'] : []),
                'codeHtml' => $this->renderCodeExcerpt($ff, $ll),
            ];
            $idx++;
        }

        $labels = [
            'headings' => [
                'details' => Translator::t($config, 'html.headings.details'),
                'suggestions' => Translator::t($config, 'html.headings.suggestions'),
                'stack' => Translator::t($config, 'html.headings.stack'),
                'globals' => Translator::t($config, 'html.headings.globals'),
            ],
            'labels' => [
                'arguments' => Translator::t($config, 'html.labels.arguments'),
                'locals' => Translator::t($config, 'html.labels.locals'),
                'code' => Translator::t($config, 'html.labels.code'),
                'get' => Translator::t($config, 'html.labels.get'),
                'post' => Translator::t($config, 'html.labels.post'),
                'cookie' => Translator::t($config, 'html.labels.cookie'),
                'session' => Translator::t($config, 'html.labels.session'),
            ],
        ];

        $globals = isset($explanation['globals']) && is_array($explanation['globals']) ? $explanation['globals'] : [];
        $globalsDumps = [
            'get' => $this->dumpArgs(isset($globals['get']) ? $globals['get'] : []),
            'post' => $this->dumpArgs(isset($globals['post']) ? $globals['post'] : []),
            'cookie' => $this->dumpArgs(isset($globals['cookie']) ? $globals['cookie'] : []),
            'session' => $this->dumpArgs(isset($globals['session']) ? $globals['session'] : []),
        ];

        return [
            'docLang' => $docLang,
            'title' => $title,
            'severity' => $severity,
            'where' => $where,
            'summary' => $summary,
            'verbose' => (bool)$config->verbose,
            'details' => $details,
            'suggestions' => $suggestions,
            'frames' => $frames,
            'labels' => $labels,
            'globalsDumps' => $globalsDumps,
        ];
    }

    private function dumpArgs($value, int $depthLimit = 3, int $maxItems = 20, int $maxString = 200): string
    {
        return $this->dumpValue($value, $depthLimit, $maxItems, $maxString);
    }

    private function dumpValue($value, int $depth, int $maxItems, int $maxString, array &$seen = []): string
    {
        if ($depth < 0) { return '…'; }
        if (is_null($value)) { return 'null'; }
        if (is_bool($value)) { return $value ? 'true' : 'false'; }
        if (is_int($value) || is_float($value)) { return (string)$value; }
        if (is_string($value)) {
            $s = $value;
            if (strlen($s) > $maxString) {
                $s = substr($s, 0, $maxString) . '…';
            }
            return '"' . $s . '"';
        }
        if (is_array($value)) {
            $out = "[\n";
            $i = 0; $count = count($value);
            foreach ($value as $k => $v) {
                if ($i >= $maxItems) { $out .= "  …(+" . ($count - $i) . " items)\n"; break; }
                $out .= '  [' . (is_int($k) ? $k : var_export($k, true)) . '] => ' . $this->dumpValue($v, $depth - 1, $maxItems, $maxString, $seen) . "\n";
                $i++;
            }
            $out .= ']';
            return $out;
        }
        if (is_object($value)) {
            $id = spl_object_hash($value);
            if (isset($seen[$id])) { return 'Object(' . get_class($value) . ') {…recursion…}'; }
            $seen[$id] = true;
            $cls = get_class($value);
            $out = 'Object(' . $cls . ')';
            if ($depth > 0) {
                $props = [];
                try { $props = get_object_vars($value); } catch (\Throwable $e) { $props = []; }
                if (!empty($props)) {
                    $out .= " {\n";
                    $i = 0; $count = count($props);
                    foreach ($props as $k => $v) {
                        if ($i >= $maxItems) { $out .= "  …(+" . ($count - $i) . " props)\n"; break; }
                        $out .= '  [' . $k . '] => ' . $this->dumpValue($v, $depth - 1, $maxItems, $maxString, $seen) . "\n";
                        $i++;
                    }
                    $out .= '}';
                }
            }
            return $out;
        }
        if (is_resource($value)) {
            return 'resource(' . get_resource_type($value) . ')';
        }
        return 'unknown';
    }

    private function renderCodeExcerpt(?string $file, ?int $line, int $radius = 5): string
    {
        if (!$file || !$line || !is_file($file) || $line < 1) { return ''; }
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) { return ''; }
        $total = count($lines);
        $start = max(1, $line - $radius);
        $end = min($total, $line + $radius);
        $html = '';
        for ($i = $start; $i <= $end; $i++) {
            $content = isset($lines[$i - 1]) ? htmlspecialchars($lines[$i - 1], ENT_QUOTES, 'UTF-8') : '';
            $hl = ($i === $line) ? ' hl' : '';
            $html .= '<div class="line' . $hl . '"><span class="ln">' . $i . '</span>' . $content . '</div>';
        }
        return $html;
    }
}
