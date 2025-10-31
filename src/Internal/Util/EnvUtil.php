<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function in_array;

use const PHP_SAPI;

/**
 * EnvUtil centralizes access to environment and runtime information.
 *
 * Why: isolating PHP core calls (like getenv, SAPI checks) improves testability
 * and keeps high-level classes free from low-level details.
 */
final class EnvUtil
{
    /**
     * Reads an environment variable returning an empty string if missing.
     * Why: `getenv()` can return false; we normalize to string to simplify callers and
     * avoid repetitive type/missing checks.
     */
    public function getEnv(string $name): string
    {
        $v = getenv($name);

        return false === $v ? '' : (string) $v;
    }

    /**
     * Returns the current working directory or empty string if unavailable.
     * Why: `getcwd()` can fail (e.g., directory removed); we normalize the output to
     * avoid exceptions/notices and simplify downstream flow.
     */
    public function getCwd(): string
    {
        $cwd = in_array($this->getEnv('PHP_ERROR_INSIGHT_ROOT'), ['', '0'], true) ? getcwd() : $this->getEnv('PHP_ERROR_INSIGHT_ROOT');

        return false === $cwd ? '' : $cwd;
    }

    /**
     * Returns the current SAPI string.
     * Why: exposing `PHP_SAPI` via a method makes it easier to stub in tests.
     */
    public function phpSapi(): string
    {
        return PHP_SAPI;
    }

    /**
     * Detects whether the context is CLI-like (cli or phpdbg).
     * Why: some features (colors, headers) depend on the SAPI; this helper avoids duplication
     * and keeps the logic in a single place.
     */
    public function isCliLike(): bool
    {
        $s = $this->phpSapi();

        return 'cli' === $s || 'phpdbg' === $s;
    }
}
