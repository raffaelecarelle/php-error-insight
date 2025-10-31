<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

/**
 * RequestUtil exposes superglobals in a controlled way.
 */
final class RequestUtil
{
    /**
     * Returns the query parameters.
     * Why: encapsulating access to `$_GET` makes testing easier and reduces coupling with superglobals.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $_GET;
    }

    /**
     * Returns body parameters (POST form-data/x-www-form-urlencoded).
     * Why: separating the reading of `$_POST` clarifies access points and makes mocking easier.
     *
     * @return array<string,mixed>
     */
    public function post(): array
    {
        return $_POST;
    }

    /**
     * Returns request cookies.
     * Why: accessing `$_COOKIE` via a wrapper simplifies checks and testing.
     *
     * @return array<string,mixed>
     */
    public function cookie(): array
    {
        return $_COOKIE;
    }

    /**
     * Returns the contents of `$_SERVER`.
     * Why: isolating superglobals allows simulating the environment in tests.
     *
     * @return array<string,mixed>
     */
    public function server(): array
    {
        return $_SERVER;
    }
}
