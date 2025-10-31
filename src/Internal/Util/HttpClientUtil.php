<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function function_exists;
use function is_array;
use function strlen;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;

/**
 * Small HTTP JSON client built on cURL, wrapped for testability and to isolate PHP core calls.
 */
final class HttpClientUtil
{
    public function __construct(
        private readonly EnvUtil $env = new EnvUtil(),
        private readonly HttpUtil $http = new HttpUtil(),
        private readonly StringUtil $str = new StringUtil(),
    ) {
    }

    /**
     * Performs an HTTP request with a JSON payload and decodes the JSON response.
     * Why:
     * - We use cURL for reliability and fine-grained timeout control.
     * - We set `Content-Length` for compatibility with some servers/proxies.
     * - `CURLOPT_CONNECTTIMEOUT` is capped at 5s to fail fast during the handshake, while
     *   `CURLOPT_TIMEOUT` governs the total duration according to `timeoutSec`.
     * - We return `null` on network errors or non-2xx status codes to signal failure without
     *   throwing exceptions in a low-level utility.
     * - We decode to an associative array and verify the result is actually an array.
     *
     * @param array<string,mixed> $payload Data to serialize as JSON in the body
     * @param array<int,string>   $headers Additional headers to send
     *
     * @return array<string,mixed>|null Decoded JSON or null on error/non-2xx/invalid JSON
     */
    public function requestJson(string $method, string $url, array $payload, array $headers, int $timeoutSec): ?array
    {
        if (!function_exists('curl_init')) {
            // For environments without the cURL extension we prefer a neutral fallback (null)
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

        // Usiamo CUSTOMREQUEST per supportare anche metodi oltre POST/GET (PUT, PATCH, DELETE)
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

    /**
     * Emit a machine-readable representation for integrations.
     *
     * Why set headers here: when running under a web SAPI we owe a proper content-type and a 500 status code
     * so upstream reverse proxies and clients can react appropriately.
     */
    /**
     * Detect whether the incoming HTTP request declares a JSON content type.
     */
    public function isHttpJsonRequest(): bool
    {
        if ($this->env->isCliLike()) {
            return false;
        }

        $contentType = '';
        $accept = '';

        // Prefer server-provided headers when available
        $headers = $this->http->getAllHeaders();
        foreach ($headers as $name => $value) {
            $lname = $this->str->toLower($name);
            if ('content-type' === $lname) {
                $contentType = $value;
            } elseif ('accept' === $lname) {
                $accept = $value;
            }
        }

        if ('' === $contentType) {
            $ct = $this->http->serverVar('CONTENT_TYPE');
            if ('' === $ct) {
                $ct = $this->http->serverVar('HTTP_CONTENT_TYPE');
            }

            $contentType = $ct;
        }

        if ('' === $accept) {
            $accept = $this->http->serverVar('HTTP_ACCEPT');
        }

        $ct = $this->str->toLower($this->str->trim($contentType));
        $ac = $this->str->toLower($this->str->trim($accept));

        // If either Content-Type or Accept declares JSON (including +json or json-p), force JSON output.
        if ('' !== $ct && $this->str->contains($ct, 'json')) {
            return true;
        }

        return '' !== $ac && $this->str->contains($ac, 'json');
    }
}
