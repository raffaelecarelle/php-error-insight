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
     * Converte in minuscolo secondo l'implementazione nativa.
     * Perché: incapsulare `strtolower()` rende il comportamento sostituibile/testabile.
     */
    public function toLower(string $s): string
    {
        return strtolower($s);
    }

    /**
     * Rimuove spazi bianchi ai bordi.
     * Perché: centralizziamo `trim()` per coerenza con le altre util e facilità di test.
     */
    public function trim(string $s): string
    {
        return trim($s);
    }

    /**
     * Restituisce la lunghezza della stringa in byte.
     * Perché: `strlen()` è veloce e adatta per calcoli non sensibili a multibyte; se servisse
     * consapevolezza multibyte si introdurrebbe un util separato.
     */
    public function length(string $s): int
    {
        return strlen($s);
    }

    /**
     * Verifica la presenza di una sottostringa.
     * Perché: `str_contains()` è esplicita e sicura rispetto a confronti con `false` di `strpos()`.
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
     * Esegue sostituzioni multiple in un'unica passata.
     * Perché: passare array di search/replace a `str_replace()` evita loop manuali.
     *
     * @param array<string> $search
     * @param array<string> $replace
     */
    public function replaceMany(string $s, array $search, array $replace): string
    {
        return str_replace($search, $replace, $s);
    }

    /**
     * Padding a sinistra fino a una lunghezza target.
     * Perché: `str_pad()` con `STR_PAD_LEFT` è più leggibile di costruzioni manuali.
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
