<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function function_exists;
use function is_string;

/**
 * HttpUtil wraps access to HTTP headers and server variables, and sending headers.
 */
final class HttpUtil
{
    /**
     * Checks whether a function exists in the current environment.
     * Why: some HTTP functions may be missing (e.g., `getallheaders()` in CGI);
     * this check avoids fatal errors and allows fallbacks.
     */
    public function functionExists(string $name): bool
    {
        return function_exists($name);
    }

    /**
     * Returns request headers as a string=>string array when possible.
     * Why: `getallheaders()` is not always available; we filter only string/string pairs
     * to avoid unexpected types and return [] as a consistent fallback.
     *
     * @return array<string,string>
     */
    public function getAllHeaders(): array
    {
        if ($this->functionExists('getallheaders')) {
            $h = getallheaders();
            /** @var array<string,string> $out */
            $out = [];
            foreach ($h as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    $out[$k] = $v;
                }
            }

            return $out;
        }

        return [];
    }

    /**
     * Indicates whether HTTP headers have already been sent.
     * Why: sending headers after output triggers warnings; this check enables safe decisions.
     */
    public function headersSent(): bool
    {
        return headers_sent();
    }

    /**
     * Invia un header HTTP, con opzione di sostituzione e code opzionale.
     * Perché: incapsulare `header()` consente di centralizzare il comportamento e semplificare i test.
     */
    public function sendHeader(string $header, ?bool $replace = true, ?int $responseCode = null): void
    {
        if (null === $responseCode) {
            header($header, $replace ?? true);
        } else {
            header($header, $replace ?? true, $responseCode);
        }
    }

    /**
     * Imposta il codice di risposta HTTP.
     * Perché: wrapper semplice per mantenere consistenza con le altre util.
     */
    public function setResponseCode(int $code): void
    {
        http_response_code($code);
    }

    /**
     * Legge una variabile da `$_SERVER` e normalizza a stringa.
     * Perché: `$_SERVER` può contenere tipi non-stringa; convergiamo su stringa vuota come fallback.
     */
    public function serverVar(string $key): string
    {
        $v = $_SERVER[$key] ?? '';

        return is_string($v) ? $v : '';
    }
}
