<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\AIClientInterface;
use PhpErrorInsight\Contracts\ExplainerInterface;

use function function_exists;
use function in_array;
use function is_array;
use function is_string;
use function strlen;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;
use const E_COMPILE_ERROR;
use const E_COMPILE_WARNING;
use const E_CORE_ERROR;
use const E_CORE_WARNING;
use const E_DEPRECATED;
use const E_ERROR;
use const E_NOTICE;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;
use const E_STRICT;
use const E_USER_DEPRECATED;
use const E_USER_ERROR;
use const E_USER_NOTICE;
use const E_USER_WARNING;
use const E_WARNING;
use const PHP_SESSION_ACTIVE;

/**
 * Produces human-friendly explanations for runtime problems and optionally enriches them via an AI backend.
 *
 * Why separate from rendering and handling:
 * - Keeps the knowledge/heuristics about errors isolated and testable.
 * - Allows swapping the AI provider without changing how we render or capture errors.
 */
final class Explainer implements ExplainerInterface
{
    public function __construct(private readonly ?AIClientInterface $aiClient = null)
    {
    }

    /**
     * Build an educational explanation based on the given error/exception data.
     *
     * Why we attach both normalized trace and minimal globals:
     * - Normalized trace enables consistent presentation across formats.
     * - Minimal globals snapshot helps reproducing issues without leaking too much data.
     *
     * Returns an associative array with keys: title, summary, details, suggestions, severityLabel, original.
     *
     * @param string                              $kind  'error'|'exception'|'shutdown'
     * @param array<int,array<string,mixed>>|null $trace
     *
     * @return array<string,mixed>
     */
    public function explain(string $kind, string $message, ?string $file, ?int $line, ?array $trace, ?int $severity, Config $config): array
    {
        $severityLabel = null !== $severity ? $this->severityToString($severity) : ('exception' === $kind ? 'Exception' : 'Error');

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
            'trace' => is_array($trace) ? $this->normalizeTrace($trace) : [],
            // Capture a minimal snapshot of superglobals to help debugging
            'globals' => [
                'get' => $_GET,
                'post' => $_POST,
                'cookie' => $_COOKIE,
                'session' => (fn (): array => (function_exists('session_status') && PHP_SESSION_ACTIVE === session_status() && isset($_SESSION)) ? $_SESSION : [])(),
            ],
        ];

        if ('none' !== $config->backend) {
            $aiText = $this->aiExplain($kind, $message, $file, $line, $severity, $config);
            if (is_string($aiText) && '' !== trim($aiText)) {
                $explanation['title'] = Translator::t($config, 'title.ai');

                // Try to extract bullet suggestions from AI text
                $aiTrim = trim($aiText);
                $aiLines = preg_split('/\r?\n/', $aiTrim) ?: [];
                $bullets = [];
                foreach ($aiLines as $ln) {
                    $t = trim($ln);
                    if ('' === $t) {
                        continue;
                    }

                    if (preg_match('/^[-*•]\s+(.+)/u', $t, $m)) {
                        $bullets[] = trim($m[1]);
                    } elseif (preg_match('/^\d+\.?\s+(.+)/', $t, $m)) {
                        $bullets[] = trim($m[1]);
                    }
                }

                if ([] !== $bullets) {
                    // Merge new unique suggestions
                    $existing = $explanation['suggestions'];
                    foreach ($bullets as $b) {
                        if (!in_array($b, $existing, true)) {
                            $existing[] = $b;
                        }
                    }

                    $explanation['suggestions'] = $existing;
                }

                $explanation['details'] .= '[AI] ' . $aiText;
            }
        }

        return $explanation;
    }

    private function severityToString(int $severity): string
    {
        return match ($severity) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'E_' . $severity,
        };
    }

    /**
     * @param array<int, mixed> $trace
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTrace(array $trace): array
    {
        $out = [];
        foreach ($trace as $f) {
            if (!is_array($f)) {
                continue;
            }

            $fn = isset($f['function']) ? (string) $f['function'] : '';
            $cls = isset($f['class']) ? (string) $f['class'] : '';
            $type = isset($f['type']) ? (string) $f['type'] : '';
            $file = isset($f['file']) ? (string) $f['file'] : null;
            $line = isset($f['line']) ? (int) $f['line'] : null;

            $out[] = [
                'function' => $fn,
                'class' => $cls,
                'type' => $type,
                'file' => $file,
                'line' => $line,
            ];
        }

        return $out;
    }

    private function aiExplain(string $kind, string $message, ?string $file, ?int $line, ?int $severity, Config $config): ?string
    {
        $lang = '' !== $config->language && '0' !== $config->language ? $config->language : 'en';
        $sev = null !== $severity ? $this->severityToString($severity) : ('exception' === $kind ? 'Exception' : 'Error');
        $where = (null !== $file ? ($file . (null !== $line ? ":{$line}" : '')) : Translator::t($config, 'details.unknown'));
        $prompt = "You are an assistant that explains PHP errors in {$lang}.
Message: {$message}
Severity: {$sev}
Location: {$where}
Provide a concise Summary (max 500 chars) and concise Details (max 500 chars). Then list up to 2 practical fix suggestions as bullet points (-). Keep wording tight and actionable.";

        // Sanitize prompt before sending to any AI backend
        $sanitizer = new DefaultSensitiveDataSanitizer();
        $sanCfg = new SanitizerConfig();
        $envSanitize = getenv('PHP_ERROR_INSIGHT_SANITIZE');
        $shouldSanitize = false === $envSanitize ? true : in_array(strtolower((string) $envSanitize), ['1', 'true', 'yes', 'on'], true);
        if ($shouldSanitize) {
            $rules = getenv('PHP_ERROR_INSIGHT_SANITIZE_RULES');
            if (is_string($rules) && '' !== $rules) {
                $sanCfg->enabledRules = array_map('trim', array_filter(explode(',', $rules)));
            }

            $mask = getenv('PHP_ERROR_INSIGHT_SANITIZE_MASK');
            if (is_string($mask) && '' !== $mask) {
                $sanCfg->masks['default'] = $mask;
            }

            $prompt = $sanitizer->sanitize($prompt, $sanCfg);
        }

        // If an AI client has been injected, delegate to it (for DI/testing/extension)
        if ($this->aiClient instanceof AIClientInterface) {
            return $this->aiClient->generateExplanation($prompt, $config);
        }

        // Fallback to built-in simple backends to preserve backward compatibility
        $backend = strtolower(trim($config->backend));
        if ('local' === $backend) {
            return $this->aiLocal($prompt, $config);
        }

        if ('api' === $backend || 'openai' === $backend) {
            return $this->aiOpenAI($prompt, $config);
        }

        if ('anthropic' === $backend) {
            return $this->aiAnthropic($prompt, $config);
        }

        if ('google' === $backend || 'gemini' === $backend) {
            return $this->aiGoogle($prompt, $config);
        }

        return null;
    }

    private function aiLocal(string $prompt, Config $config): ?string
    {
        if (null === $config->model || '' === $config->model || '0' === $config->model) {
            return null;
        }

        $base = null !== $config->apiUrl && '' !== $config->apiUrl && '0' !== $config->apiUrl ? $config->apiUrl : 'http://localhost:11434'; // Ollama default
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
            return trim($resp['choices'][0]['message']['content']);
        }

        return null;
    }

    private function aiOpenAI(string $prompt, Config $config): ?string
    {
        if (null === $config->apiKey || '' === $config->apiKey || '0' === $config->apiKey || (null === $config->model || '' === $config->model || '0' === $config->model)) {
            return null;
        }

        $url = null !== $config->apiUrl && '' !== $config->apiUrl && '0' !== $config->apiUrl ? $config->apiUrl : 'https://api.openai.com/v1/chat/completions';
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
            return trim($resp['choices'][0]['message']['content']);
        }

        if (isset($resp['error']['message']) && is_string($resp['error']['message'])) {
            return null; // avoid exposing API errors to end-user
        }

        return null;
    }

    private function aiAnthropic(string $prompt, Config $config): ?string
    {
        if (null === $config->apiKey || '' === $config->apiKey || '0' === $config->apiKey || (null === $config->model || '' === $config->model || '0' === $config->model)) {
            return null;
        }

        $url = null !== $config->apiUrl && '' !== $config->apiUrl && '0' !== $config->apiUrl ? $config->apiUrl : 'https://api.anthropic.com/v1/messages';
        $headers = [
            'x-api-key: ' . $config->apiKey,
            'anthropic-version: 2023-06-01',
        ];
        $payload = [
            'model' => $config->model,
            'max_tokens' => 400,
            'messages' => [
                ['role' => 'user', 'content' => [['type' => 'text', 'text' => $prompt]]],
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
            return trim($resp['content'][0]['text']);
        }

        // Some SDKs nest under content[0]['type' => 'text']
        if (isset($resp['content'][0]) && is_array($resp['content'][0]) && isset($resp['content'][0]['type']) && 'text' === $resp['content'][0]['type'] && isset($resp['content'][0]['text'])) {
            return trim((string) $resp['content'][0]['text']);
        }

        return null;
    }

    private function aiGoogle(string $prompt, Config $config): ?string
    {
        if (null === $config->apiKey || '' === $config->apiKey || '0' === $config->apiKey || (null === $config->model || '' === $config->model || '0' === $config->model)) {
            return null;
        }

        $base = null !== $config->apiUrl && '' !== $config->apiUrl && '0' !== $config->apiUrl ? $config->apiUrl : 'https://generativelanguage.googleapis.com/v1/models';
        $url = rtrim($base, '/') . '/' . rawurlencode($config->model) . ':generateContent?key=' . urlencode($config->apiKey);
        $headers = [];
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
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
            return trim($resp['candidates'][0]['content']['parts'][0]['text']);
        }

        // Some responses may place text at candidates[0].output_text (older beta)
        if (isset($resp['candidates'][0]['output_text']) && is_string($resp['candidates'][0]['output_text'])) {
            return trim($resp['candidates'][0]['output_text']);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string>   $headers
     *
     * @return array<string,mixed>|null
     */
    private function httpJson(string $method, string $url, array $payload, array $headers, int $timeoutSec): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init();
        if (false === $ch) {
            return null;
        }

        $json = json_encode($payload);
        $baseHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen((string) $json),
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
        if (false === $result) {
            curl_close($ch);

            return null;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode((string) $result, true);

        return is_array($decoded) ? $decoded : null;
    }
}
