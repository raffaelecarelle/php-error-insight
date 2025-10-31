<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_array;

/**
 * ArrayUtil centralizes common array operations and type checks.
 */
final class ArrayUtil
{
    /**
     * Checks if the value is an array using the native function.
     * Why: centralizing type checks makes testing (stubs/mocks) easier and keeps
     * high-level code free from direct core function calls.
     */
    public function isArray(mixed $v): bool
    {
        return is_array($v);
    }

    /**
     * Counts the elements of an array.
     * Why: wrapping `count()` helps keep strong type signatures and allows
     * potential abstraction in tests (empty arrays, etc.).
     *
     * @param array<mixed> $a
     */
    public function count(array $a): int
    {
        return count($a);
    }

    /**
     * Returns unique values while preserving a numerically indexed array (list).
     * Why: `array_unique()` keeps original keys; `array_values()` reindexes so we get
     * a stable and predictable `list<mixed>`.
     *
     * @param array<mixed> $a
     *
     * @return list<mixed>
     */
    public function unique(array $a): array
    {
        return array_values(array_unique($a));
    }

    /**
     * Applies a function to all elements.
     * Why: `array_map()` is C-optimized and preserves expected performance and semantics;
     * the wrapper keeps code testable and consistent with other utils.
     *
     * @param array<mixed>          $a
     * @param callable(mixed):mixed $fn
     *
     * @return array<mixed>
     */
    public function map(array $a, callable $fn): array
    {
        return array_map($fn, $a);
    }

    /**
     * Filters the elements and reindexes the resulting array.
     * Why: `array_filter()` preserves original keys; using `array_values()` ensures a
     * sequential list, useful when order/index matters.
     *
     * @param array<mixed>              $a
     * @param callable(mixed):bool|null $fn
     *
     * @return list<mixed>
     */
    public function filter(array $a, ?callable $fn = null): array
    {
        return array_values(array_filter($a, $fn));
    }

    /**
     * Returns the first element or a default value.
     * Why: avoids notices on missing keys and makes the fallback explicit.
     *
     * @param array<mixed> $a
     */
    public function first(array $a, mixed $default = null): mixed
    {
        return $a[0] ?? $default;
    }

    /**
     * Searches using strict comparison (===) to avoid type coercions.
     * Why: `in_array()` with the third parameter `true` prevents false positives caused by
     * type juggling (e.g., 0 vs '0').
     *
     * @param array<mixed> $haystack
     */
    public function inArrayStrict(mixed $needle, array $haystack): bool
    {
        return in_array($needle, $haystack, true);
    }
}
