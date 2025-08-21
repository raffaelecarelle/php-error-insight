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
        $title = htmlspecialchars((string)($explanation['title'] ?? 'PHP Error Explainer'), ENT_QUOTES, 'UTF-8');
        $summary = htmlspecialchars((string)($explanation['summary'] ?? ''), ENT_QUOTES, 'UTF-8');
        $severity = htmlspecialchars((string)($explanation['severityLabel'] ?? 'Error'), ENT_QUOTES, 'UTF-8');
        $original = isset($explanation['original']) && is_array($explanation['original']) ? $explanation['original'] : [];
        $file = isset($original['file']) ? (string)$original['file'] : '';
        $line = isset($original['line']) ? (string)$original['line'] : '';
        $where = htmlspecialchars(trim($file . ($line !== '' ? ":$line" : '')), ENT_QUOTES, 'UTF-8');
        $details = (string)($explanation['details'] ?? '');
        $detailsHtml = '<pre style="white-space:pre-wrap;margin:0">' . htmlspecialchars($details, ENT_QUOTES, 'UTF-8') . '</pre>';

        $suggestionsHtml = '';
        if (!empty($explanation['suggestions']) && is_array($explanation['suggestions'])) {
            $suggestionsHtml .= '<ul style="margin:0 0 0 1.2em">';
            foreach ($explanation['suggestions'] as $s) {
                $suggestionsHtml .= '<li>' . htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $suggestionsHtml .= '</ul>';
        }

        $docLang = htmlspecialchars($config->language ?: 'it', ENT_QUOTES, 'UTF-8');
        $hDetails = htmlspecialchars(Translator::t($config, 'html.headings.details'), ENT_QUOTES, 'UTF-8');
        $hSuggestions = htmlspecialchars(Translator::t($config, 'html.headings.suggestions'), ENT_QUOTES, 'UTF-8');
        $hStack = htmlspecialchars(Translator::t($config, 'html.headings.stack'), ENT_QUOTES, 'UTF-8');
        $hGlobals = htmlspecialchars(Translator::t($config, 'html.headings.globals'), ENT_QUOTES, 'UTF-8');
        $lArgs = htmlspecialchars(Translator::t($config, 'html.labels.arguments'), ENT_QUOTES, 'UTF-8');
        $lCode = htmlspecialchars(Translator::t($config, 'html.labels.code'), ENT_QUOTES, 'UTF-8');
        $lGET = htmlspecialchars(Translator::t($config, 'html.labels.get'), ENT_QUOTES, 'UTF-8');
        $lPOST = htmlspecialchars(Translator::t($config, 'html.labels.post'), ENT_QUOTES, 'UTF-8');
        $lCOOKIE = htmlspecialchars(Translator::t($config, 'html.labels.cookie'), ENT_QUOTES, 'UTF-8');
        $lSESSION = htmlspecialchars(Translator::t($config, 'html.labels.session'), ENT_QUOTES, 'UTF-8');

        $trace = isset($explanation['trace']) && is_array($explanation['trace']) ? $explanation['trace'] : [];
        $globals = isset($explanation['globals']) && is_array($explanation['globals']) ? $explanation['globals'] : [];

        echo '<!DOCTYPE html><html lang="' . $docLang . '"><head><meta charset="utf-8"><title>' . $title . '</title>' .
            '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu, Cantarell, Noto Sans, Helvetica, Arial, sans-serif;padding:24px;background:#fafafa;color:#222}' .
            '.card{max-width:1000px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.06)}' .
            '.hdr{padding:16px 20px;border-bottom:1px solid #f0f0f0;background:#f9fafb;border-radius:10px 10px 0 0}' .
            '.title{margin:0;font-size:20px;font-weight:700}' .
            '.sev{font-size:12px;color:#6b7280}' .
            '.body{padding:20px}' .
            '.where{font-family:monospace;color:#374151;margin:4px 0 12px 0}' .
            '.summary{margin:12px 0;font-size:16px}' .
            '.section{margin-top:14px}' .
            '.section h3{margin:0 0 6px 0;font-size:14px;color:#374151}' .
            '.stack{border:1px solid #eee;border-radius:8px;overflow:hidden}' .
            '.frame{border-top:1px solid #f3f4f6}' .
            '.frame:first-child{border-top:none}' .
            '.frame-h{padding:10px 12px;display:flex;gap:10px;align-items:center;cursor:pointer;background:#fafafa;font-family:system-ui;font-size:13px}' .
            '.frame-h.active{background:#eef2ff}' .
            '.frame-h .idx{font-weight:700;color:#6b7280;width:28px;text-align:right}' .
            '.frame-h .sig{flex:1 1 auto;overflow:auto;white-space:nowrap}' .
            '.frame-h .loc{color:#6b7280;font-family:monospace}' .
            '.frame-b{display:none;padding:12px;border-top:1px solid #e5e7eb;background:#fff}' .
            '.frame-b.active{display:block}' .
            '.code{border:1px solid #e5e7eb;border-radius:6px;background:#0b1021;color:#d6deeb;font-family:monospace;font-size:12px;overflow:auto}' .
            '.code .ln{display:inline-block;width:3em;padding:0 8px 0 8px;color:#9aa4b2;background:#0a0e1a;text-align:right;user-select:none}' .
            '.code .line{display:block;padding:0 8px}' .
            '.code .hl{background:#1f2937}' .
            '.kv{font-family:monospace;font-size:12px;white-space:pre-wrap;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px;overflow:auto}' .
            '</style>' .
            '<script>function __ee_sel(i){var h=document.querySelectorAll(".frame-h"),b=document.querySelectorAll(".frame-b");h.forEach(function(e,idx){if(idx===i){e.classList.add("active");}else{e.classList.remove("active");}});b.forEach(function(e,idx){if(idx===i){e.classList.add("active");}else{e.classList.remove("active");}});}</script>' .
            '</head><body><div class="card">';
        echo '<div class="hdr"><div class="title">' . $title . '</div><div class="sev">' . $severity . '</div></div>';
        echo '<div class="body">';
        if ($where !== '') {
            echo '<div class="where">' . $where . '</div>';
        }
        if ($summary !== '') {
            echo '<div class="summary">' . $summary . '</div>';
        }
        // Stack trace UI
        if (!empty($trace)) {
            echo '<div class="section"><h3>' . $hStack . '</h3><div class="stack">';
            $idx = 0;
            foreach ($trace as $frame) {
                $fn = isset($frame['function']) ? (string)$frame['function'] : 'unknown';
                $cls = isset($frame['class']) ? (string)$frame['class'] : '';
                $type = isset($frame['type']) ? (string)$frame['type'] : '';
                $sig = htmlspecialchars(trim($cls . $type . $fn . '()'), ENT_QUOTES, 'UTF-8');
                $ff = isset($frame['file']) ? (string)$frame['file'] : '';
                $ll = isset($frame['line']) ? (int)$frame['line'] : 0;
                $loc = htmlspecialchars(($ff !== '' ? ($ff . ($ll ? ':' . $ll : '')) : ''), ENT_QUOTES, 'UTF-8');
                echo '<div class="frame">';
                echo '<div class="frame-h" onclick="__ee_sel(' . $idx . ')"><div class="idx">#' . $idx . '</div><div class="sig">' . $sig . '</div><div class="loc">' . $loc . '</div></div>';
                echo '<div class="frame-b">';
                // Arguments
                $argsOut = $this->dumpArgs(isset($frame['args']) && is_array($frame['args']) ? $frame['args'] : []);
                echo '<div class="section"><strong>' . $lArgs . '</strong><div class="kv">' . htmlspecialchars($argsOut, ENT_QUOTES, 'UTF-8') . '</div></div>';
                // Code excerpt
                $codeHtml = $this->renderCodeExcerpt($ff, $ll);
                if ($codeHtml !== '') {
                    echo '<div class="section"><strong>' . $lCode . '</strong><div class="code">' . $codeHtml . '</div></div>';
                }
                echo '</div>';
                echo '</div>';
                $idx++;
            }
            echo '</div></div>';
            // Activate first frame by default
            echo '<script>__ee_sel(0);</script>';
        }
        // Verbose textual details as fallback
        if ($config->verbose && $details !== '') {
            echo '<div class="section"><h3>' . $hDetails . '</h3>' . $detailsHtml . '</div>';
        }
        // Suggestions
        if ($suggestionsHtml !== '') {
            echo '<div class="section"><h3>' . $hSuggestions . '</h3>' . $suggestionsHtml . '</div>';
        }
        // Globals snapshot
        if (!empty($globals)) {
            echo '<div class="section"><h3>' . $hGlobals . '</h3>';
            $gget = isset($globals['get']) ? $globals['get'] : [];
            $gpost = isset($globals['post']) ? $globals['post'] : [];
            $gsess = isset($globals['session']) ? $globals['session'] : [];
            $gcook = isset($globals['cookie']) ? $globals['cookie'] : [];
            echo '<div class="section"><strong>' . $lGET . '</strong><div class="kv">' . htmlspecialchars($this->dumpArgs($gget), ENT_QUOTES, 'UTF-8') . '</div></div>';
            echo '<div class="section"><strong>' . $lPOST . '</strong><div class="kv">' . htmlspecialchars($this->dumpArgs($gpost), ENT_QUOTES, 'UTF-8') . '</div></div>';
            echo '<div class="section"><strong>' . $lSESSION . '</strong><div class="kv">' . htmlspecialchars($this->dumpArgs($gsess), ENT_QUOTES, 'UTF-8') . '</div></div>';
            echo '<div class="section"><strong>' . $lCOOKIE . '</strong><div class="kv">' . htmlspecialchars($this->dumpArgs($gcook), ENT_QUOTES, 'UTF-8') . '</div></div>';
            echo '</div>';
        }
        echo '</div></div></body></html>';
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
