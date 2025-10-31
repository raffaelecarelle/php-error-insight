<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function explode;
use function implode;
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

    public function capitalaze(string $s): string
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
     * Sostituisce la prima occorrenza di ricerca con il valore indicato su tutta la stringa.
     * Perché: `str_replace()` è più semplice e veloce quando non serve regex.
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
     * Formatta stringhe in stile printf.
     * Perché: accettiamo variadic per pass-through diretto a `sprintf()` mantenendo tipizzazione.
     */
    public function sprintf(string $fmt, mixed ...$args): string
    {
        return sprintf($fmt, ...$args);
    }

    /**
     * Unisce elementi con una colla.
     * Perché: wrapper per coerenza API e facilità di stub nei test.
     *
     * @param array<string> $pieces
     */
    public function implode(string $glue, array $pieces): string
    {
        return implode($glue, $pieces);
    }

    /**
     * Divide una stringa in base a un delimitatore in un array di stringhe.
     * Perché: wrapper per coerenza con le altre operazioni di stringa.
     *
     * @return array<string>
     */
    public function explode(string $delimiter, string $string): array
    {
        return explode($delimiter, $string);
    }
}
