<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function explode;
use function implode;
use function is_string;
use function sprintf;
use function str_contains;
use function str_pad;
use function str_replace;
use function strlen;
use function strtolower;
use function trim;

use const STR_PAD_LEFT;

/**
 * StringUtil wraps common string operations to keep high-level classes free of core functions.
 */
final class StringUtil
{
    /**
     * Converts to lowercase using the native implementation.
     * Why: encapsulating `strtolower()` makes the behavior swappable/testable.
     */
    public function toLower(string $s): string
    {
        return strtolower($s);
    }

    public function capitalize(string $s): string
    {
        return ucfirst($s);
    }

    /**
     * Removes leading and trailing whitespace.
     * Why: centralizing `trim()` keeps consistency with other utils and eases testing.
     */
    public function trim(string $s): string
    {
        return trim($s);
    }

    /**
     * Returns the string length in bytes.
     * Why: `strlen()` is fast and suitable for calculations not sensitive to multibyte; if multibyte
     * awareness were needed, a separate util would be introduced.
     */
    public function length(string $s): int
    {
        return strlen($s);
    }

    /**
     * Checks for the presence of a substring.
     * Why: `str_contains()` is explicit and safer than comparing `strpos()` with false.
     */
    public function contains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    /**
     * Replaces all occurrences of search with the given value in the entire string.
     * Why: `str_replace()` is simpler and faster when regex is not needed.
     */
    public function replace(string $s, string $search, string $replace): string
    {
        return str_replace($search, $replace, $s);
    }

    /**
     * Performs multiple replacements in a single pass.
     * Why: passing search/replace arrays to `str_replace()` avoids manual loops.
     *
     * @param array<string> $search
     * @param array<string> $replace
     */
    public function replaceMany(string $s, array $search, array $replace): string
    {
        return str_replace($search, $replace, $s);
    }

    /**
     * Left-pad up to a target length.
     * Why: `str_pad()` with `STR_PAD_LEFT` is more readable than manual constructions.
     */
    public function padLeft(string $s, int $length, string $pad = ' '): string
    {
        return str_pad($s, $length, $pad, STR_PAD_LEFT);
    }

    /**
     * Formats strings in printf style.
     * Why: we accept variadic for direct pass-through to `sprintf()` while maintaining type safety.
     */
    public function sprintf(string $fmt, mixed ...$args): string
    {
        return sprintf($fmt, ...$args);
    }

    /**
     * Joins elements with a glue string.
     * Why: wrapper for API consistency and ease of stubbing in tests.
     *
     * @param array<string> $pieces
     */
    public function implode(string $glue, array $pieces): string
    {
        return implode($glue, $pieces);
    }

    /**
     * Splits a string by a delimiter into an array of strings.
     * Why: wrapper for consistency with other string operations.
     *
     * @return array<string>
     */
    public function explode(string $delimiter, string $string): array
    {
        return explode($delimiter, $string);
    }

    public function isString(string $s): bool
    {
        return is_string($s);
    }
}
