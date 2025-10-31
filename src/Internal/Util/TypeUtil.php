<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function function_exists;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;

final class TypeUtil
{
    /**
     * String type check delegated to the native function.
     * Why: centralizing type checks makes testing easier and reduces reliance on core calls.
     */
    public function isString(mixed $v): bool
    {
        return is_string($v);
    }

    /**
     * Array type check.
     * Why: wrapping `is_array()` keeps access to type checks uniform across the codebase.
     */
    public function isArray(mixed $v): bool
    {
        return is_array($v);
    }

    /**
     * Integer type check.
     * Why: avoids implicit coercions and makes data assumptions explicit.
     */
    public function isInt(mixed $v): bool
    {
        return is_int($v);
    }

    /**
     * Checks whether a value is callable.
     * Why: using `is_callable()` reliably covers functions, methods, and invokable objects.
     */
    public function isCallable(mixed $v): bool
    {
        return is_callable($v);
    }

    /**
     * Checks whether a function exists.
     * Why: some features (extensions) may be unavailable; this safely probes the environment
     * before using them.
     */
    public function functionExists(string $name): bool
    {
        return function_exists($name);
    }
}
