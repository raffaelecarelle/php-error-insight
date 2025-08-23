<?php

declare(strict_types=1);

namespace ErrorExplainer\Internal;

use ErrorExplainer\Config;
use ErrorExplainer\Contracts\ExplainerInterface;
use ErrorExplainer\Contracts\AIClientInterface;

final class Explainer implements ExplainerInterface
{
    private ?AIClientInterface $aiClient;

    public function __construct(?AIClientInterface $aiClient = null)
    {
        $this->aiClient = $aiClient;
    }
    /**
     * Build an educational explanation based on the given error/exception data.
     * Returns an associative array with keys: title, summary, details, suggestions, severityLabel, original
     *
     * @param string $kind 'error'|'exception'|'shutdown'
     * @param string $message
     * @param string|null $file
     * @param int|null $line
     * @param array<int,array<string,mixed>>|null $trace
     * @param int|null $severity
     * @param Config $config
     * @return array<string,mixed>
     */
    public function explain(string $kind, string $message, ?string $file, ?int $line, ?array $trace, ?int $severity, Config $config): array
    {
        $severityLabel = $severity !== null ? self::severityToString($severity) : ($kind === 'exception' ? 'Exception' : 'Error');
        $lower = strtolower($message);

        $explanation = [
            'title' => Translator::t($config, 'title.basic'),
            'summary' => '',
            'details' => '',
            'suggestions' => [],
            'severityLabel' => $severityLabel,
            'original' => [
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ],
            // Attach normalized trace for rich HTML rendering
            'trace' => is_array($trace) ? self::normalizeTrace($trace) : [],
            // Capture a minimal snapshot of superglobals to help debugging
            'globals' => [
                'get' => isset($_GET) ? $_GET : [],
                'post' => isset($_POST) ? $_POST : [],
                'cookie' => isset($_COOKIE) ? $_COOKIE : [],
                'session' => (function () { return (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) ? $_SESSION : []; })(),
            ],
        ];

        if ($config->backend !== 'none') {
            $aiText = $this->aiExplain($kind, $message, $file, $line, $severity, $config);
            if (is_string($aiText) && trim($aiText) !== '') {
                $explanation['title'] = Translator::t($config, 'title.ai');
                // Try to extract bullet suggestions from AI text
                $aiLines = preg_split('/\r?\n/', trim($aiText)) ?: [];
                $bullets = [];
                foreach ($aiLines as $ln) {
                    $t = trim($ln);
                    if ($t === '') {
                        continue;
                    }
                    if (preg_match('/^[-*•]\s+(.+)/u', $t, $m)) {
                        $bullets[] = trim($m[1]);
                    } elseif (preg_match('/^\d+\.?\s+(.+)/', $t, $m)) {
                        $bullets[] = trim($m[1]);
                    }
                }
                if (!empty($bullets)) {
                    // Merge new unique suggestions
                    $existing = isset($explanation['suggestions']) && is_array($explanation['suggestions']) ? $explanation['suggestions'] : [];
                    foreach ($bullets as $b) {
                        if (!in_array($b, $existing, true)) {
                            $existing[] = $b;
                        }
                    }
                    $explanation['suggestions'] = $existing;
                }
                // Append AI explanation to details for visibility
                $explanation['details'] = ($explanation['details'] ?? '');
                if ($explanation['details'] !== '') {
                    $explanation['details'] .= "\n\n";
                }
                $explanation['details'] .= '[AI] ' . $aiText;
            } else {
                // On failure, add a soft suggestion if verbose
                if ($config->verbose) {
                    $explanation['suggestions'][] = Translator::t($config, 'suggestion.ai_unavailable');
                }
            }
        }

        $explanation['details'] = self::buildDetails($config, $message, $file, $line, $trace, $config->verbose) . ((isset($explanation['details']) && $explanation['details'] !== '') ? "\n\n" . (string)$explanation['details'] : '');
        return $explanation;
    }

    private static function severityToString(int $severity): string
    {
        switch ($severity) {
            case E_ERROR: return 'E_ERROR';
            case E_WARNING: return 'E_WARNING';
            case E_PARSE: return 'E_PARSE';
            case E_NOTICE: return 'E_NOTICE';
            case E_CORE_ERROR: return 'E_CORE_ERROR';
            case E_CORE_WARNING: return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: return 'E_COMPILE_WARNING';
            case E_USER_ERROR: return 'E_USER_ERROR';
            case E_USER_WARNING: return 'E_USER_WARNING';
            case E_USER_NOTICE: return 'E_USER_NOTICE';
            case E_STRICT: return 'E_STRICT';
            case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: return 'E_DEPRECATED';
            case E_USER_DEPRECATED: return 'E_USER_DEPRECATED';
            default: return 'E_'.(string)$severity;
        }
    }

    private static function normalizeTrace(array $trace): array
    {
        $out = [];
        foreach ($trace as $f) {
            if (!is_array($f)) {
                continue;
            }
            $fn = isset($f['function']) ? (string)$f['function'] : '';
            $cls = isset($f['class']) ? (string)$f['class'] : '';
            $type = isset($f['type']) ? (string)$f['type'] : '';
            $file = isset($f['file']) ? (string)$f['file'] : null;
            $line = isset($f['line']) ? (int)$f['line'] : null;
            $args = isset($f['args']) && is_array($f['args']) ? $f['args'] : [];
            $named = null;
            try {
                if ($fn !== '') {
                    if ($cls !== '') {
                        if (class_exists($cls, false) && method_exists($cls, $fn)) {
                            $ref = new \ReflectionMethod($cls, $fn);
                            $params = $ref->getParameters();
                            $namedTmp = [];
                            $i = 0;
                            foreach ($params as $p) {
                                $name = '$' . $p->getName();
                                if (array_key_exists($i, $args)) {
                                    $namedTmp[$name] = $args[$i];
                                } else {
                                    $namedTmp[$name] = '[missing]';
                                }
                                $i++;
                            }
                            $total = count($args);
                            for (; $i < $total; $i++) {
                                $namedTmp['$' . $i] = $args[$i];
                            }
                            $named = $namedTmp;
                        }
                    } else {
                        if (function_exists($fn)) {
                            $ref = new \ReflectionFunction($fn);
                            $params = $ref->getParameters();
                            $namedTmp = [];
                            $i = 0;
                            foreach ($params as $p) {
                                $name = '$' . $p->getName();
                                if (array_key_exists($i, $args)) {
                                    $namedTmp[$name] = $args[$i];
                                } else {
                                    $namedTmp[$name] = '[missing]';
                                }
                                $i++;
                            }
                            $total = count($args);
                            for (; $i < $total; $i++) {
                                $namedTmp['$' . $i] = $args[$i];
                            }
                            $named = $namedTmp;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $named = null;
            }

            $locals = [];
            if (isset($f['object'])) {
                $locals['$this'] = $f['object'];
            }
            if (is_array($named)) {
                foreach ($named as $k => $v) {
                    $locals[$k] = $v;
                }
            } elseif (!empty($args)) {
                $i = 0;
                foreach ($args as $v) {
                    $locals['$' . $i] = $v;
                    $i++;
                }
            }

            $out[] = [
                'function' => $fn,
                'class' => $cls,
                'type' => $type,
                'file' => $file,
                'line' => $line,
                'args' => is_array($named) ? $named : $args,
                'locals' => $locals,
            ];
        }
        return $out;
    }

    /**
     * @param array<int,array<string,mixed>>|null $trace
     */
    private static function buildDetails(Config $config, string $message, ?string $file, ?int $line, ?array $trace, bool $verbose): string
    {
        $unknown = Translator::t($config, 'details.unknown');
        $where = ($file !== null ? ($file.($line !== null ? ":$line" : '')) : $unknown);
        $labelMsg = Translator::t($config, 'details.message');
        $labelPos = Translator::t($config, 'details.position');
        $labelTrace = Translator::t($config, 'details.trace');
        $details = $labelMsg . ' ' . $message . "\n" . $labelPos . ' ' . $where;
        if ($verbose && $trace) {
            $details .= "\n" . $labelTrace . "\n";
            $i = 0;
            foreach ($trace as $frame) {
                $fn = isset($frame['function']) ? (string)$frame['function'] : 'unknown';
                $cls = isset($frame['class']) ? (string)$frame['class'] : '';
                $type = isset($frame['type']) ? (string)$frame['type'] : '';
                $f = isset($frame['file']) ? (string)$frame['file'] : '';
                $l = isset($frame['line']) ? (string)$frame['line'] : '';
                $details .= sprintf('#%d %s%s%s(%s:%s)' . "\n", $i, $cls, $type, $fn, $f, $l);
                $i++;
            }
        }
        return $details;
    }

    private function aiExplain(string $kind, string $message, ?string $file, ?int $line, ?int $severity, Config $config): ?string
    {
        $lang = $config->language ?: 'en';
        $sev = $severity !== null ? self::severityToString($severity) : ($kind === 'exception' ? 'Exception' : 'Error');
        $where = ($file !== null ? ($file.($line !== null ? ":$line" : '')) : Translator::t($config, 'details.unknown'));
        $prompt = "You are an assistant that explains PHP errors in $lang.
Message: $message
Severity: $sev
Location: $where
Explain the likely cause and provide practical steps to fix it. Keep the answer concise and use bullet points when useful.";

        // If an AI client has been injected, delegate to it (for DI/testing/extension)
        if ($this->aiClient) {
            return $this->aiClient->generateExplanation($prompt, $config);
        }

        // Fallback to built-in simple backends to preserve backward compatibility
        $backend = strtolower(trim($config->backend));
        if ($backend === 'local') {
            return $this->aiLocal($prompt, $config);
        }
        if ($backend === 'api' || $backend === 'openai') {
            return $this->aiOpenAI($prompt, $config);
        }
        if ($backend === 'anthropic') {
            return $this->aiAnthropic($prompt, $config);
        }
        if ($backend === 'google' || $backend === 'gemini') {
            return $this->aiGoogle($prompt, $config);
        }
        return null;
    }

    private function aiLocal(string $prompt, Config $config): ?string
    {
        if (!$config->model) {
            return null;
        }
        $base = $config->apiUrl ?: 'http://localhost:11434'; // Ollama default
        $url = rtrim($base, '/') . '/api/generate';
        $payload = [
            'model' => $config->model,
            'prompt' => $prompt,
            'stream' => false,
            // You can tweak options like temperature here if backend supports it
        ];
        $resp = $this->httpJson('POST', $url, $payload, [], 10);
        if (!is_array($resp)) {
            return null;
        }
        // Ollama returns { response: string, ... }
        if (isset($resp['response']) && is_string($resp['response'])) {
            return trim($resp['response']);
        }
        // LocalAI OpenAI-compatible servers might be used under 'local' if apiUrl points to /v1
        if (isset($resp['choices'][0]['message']['content']) && is_string($resp['choices'][0]['message']['content'])) {
            return trim((string)$resp['choices'][0]['message']['content']);
        }
        return null;
    }

    private function aiOpenAI(string $prompt, Config $config): ?string
    {
        if (!$config->apiKey || !$config->model) {
            return null;
        }
        $url = $config->apiUrl ?: 'https://api.openai.com/v1/chat/completions';
        $headers = [
            'Authorization: Bearer ' . $config->apiKey,
        ];
        $payload = [
            'model' => $config->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an assistant that explains PHP errors in an educational and concise way.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.2,
        ];
        $resp = $this->httpJson('POST', $url, $payload, $headers, 12);
        if (!is_array($resp)) {
            return null;
        }
        if (isset($resp['choices'][0]['message']['content']) && is_string($resp['choices'][0]['message']['content'])) {
            return trim((string)$resp['choices'][0]['message']['content']);
        }
        if (isset($resp['error']['message']) && is_string($resp['error']['message'])) {
            return null; // avoid exposing API errors to end-user
        }
        return null;
    }

    private function aiAnthropic(string $prompt, Config $config): ?string
    {
        if (!$config->apiKey || !$config->model) {
            return null;
        }
        $url = $config->apiUrl ?: 'https://api.anthropic.com/v1/messages';
        $headers = [
            'x-api-key: ' . $config->apiKey,
            'anthropic-version: 2023-06-01',
        ];
        $payload = [
            'model' => $config->model,
            'max_tokens' => 400,
            'messages' => [
                ['role' => 'user', 'content' => [ ['type' => 'text', 'text' => $prompt] ]],
            ],
            'system' => 'You are an assistant that explains PHP errors in an educational and concise way.',
            'temperature' => 0.2,
        ];
        $resp = $this->httpJson('POST', $url, $payload, $headers, 12);
        if (!is_array($resp)) {
            return null;
        }
        // Anthropic Messages API: content is array of blocks with type=text
        if (isset($resp['content'][0]['text']) && is_string($resp['content'][0]['text'])) {
            return trim((string)$resp['content'][0]['text']);
        }
        // Some SDKs nest under content[0]['type' => 'text']
        if (isset($resp['content'][0]) && is_array($resp['content'][0]) && isset($resp['content'][0]['type']) && $resp['content'][0]['type'] === 'text' && isset($resp['content'][0]['text'])) {
            return trim((string)$resp['content'][0]['text']);
        }
        return null;
    }

    private function aiGoogle(string $prompt, Config $config): ?string
    {
        if (!$config->apiKey || !$config->model) {
            return null;
        }
        $base = $config->apiUrl ?: 'https://generativelanguage.googleapis.com/v1/models';
        $url = rtrim($base, '/') . '/' . rawurlencode($config->model) . ':generateContent?key=' . urlencode($config->apiKey);
        $headers = [];
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [ ['text' => $prompt] ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
            ],
        ];
        $resp = $this->httpJson('POST', $url, $payload, $headers, 12);
        if (!is_array($resp)) {
            return null;
        }
        // Gemini API response: candidates[0].content.parts[0].text
        if (isset($resp['candidates'][0]['content']['parts'][0]['text']) && is_string($resp['candidates'][0]['content']['parts'][0]['text'])) {
            return trim((string)$resp['candidates'][0]['content']['parts'][0]['text']);
        }
        // Some responses may place text at candidates[0].output_text (older beta)
        if (isset($resp['candidates'][0]['output_text']) && is_string($resp['candidates'][0]['output_text'])) {
            return trim((string)$resp['candidates'][0]['output_text']);
        }
        return null;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $headers
     * @return array<string,mixed>|null
     */
    private function httpJson(string $method, string $url, array $payload, array $headers, int $timeoutSec)
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init();
        if ($ch === false) {
            return null;
        }
        $json = json_encode($payload);
        $baseHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen((string)$json),
        ];
        $allHeaders = array_merge($baseHeaders, $headers);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeoutSec));
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSec);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

        $result = curl_exec($ch);
        if ($result === false) {
            curl_close($ch);
            return null;
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status < 200 || $status >= 300) {
            return null;
        }
        $decoded = json_decode((string)$result, true);
        return is_array($decoded) ? $decoded : null;
    }
}
