<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

/**
 * RequestUtil exposes superglobals in a controlled way.
 */
final class RequestUtil
{
    /**
     * Restituisce i parametri di query.
     * Perché: incapsulare l'accesso a `$_GET` facilita test e riduce il coupling con superglobali.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $_GET;
    }

    /**
     * Restituisce i parametri del body (POST form-data/x-www-form-urlencoded).
     * Perché: separare la lettura di `$_POST` rende chiari i punti di accesso e facilita il mocking.
     *
     * @return array<string,mixed>
     */
    public function post(): array
    {
        return $_POST;
    }

    /**
     * Restituisce i cookie associati alla richiesta.
     * Perché: accedere a `$_COOKIE` via wrapper semplifica controlli e test.
     *
     * @return array<string,mixed>
     */
    public function cookie(): array
    {
        return $_COOKIE;
    }

    /**
     * Restituisce il contenuto di `$_SERVER`.
     * Perché: l'isolamento delle superglobali consente di simulare l'ambiente in test.
     *
     * @return array<string,mixed>
     */
    public function server(): array
    {
        return $_SERVER;
    }
}
