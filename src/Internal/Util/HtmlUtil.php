<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use const ENT_QUOTES;

final class HtmlUtil
{
    /**
     * Escapes a string for HTML.
     * Why: `htmlspecialchars()` protects from XSS injection; we use `ENT_QUOTES` to include
     * both single and double quotes and set encoding (UTF-8) to avoid ambiguities.
     */
    public function escape(string $s, int $flags = ENT_QUOTES, string $encoding = 'UTF-8'): string
    {
        return htmlspecialchars($s, $flags, $encoding);
    }

    /**
     * Evidenzia sintassi PHP come HTML e restituisce sempre una stringa.
     * Perché: usare `$return=true` evita output diretto e facilita l'iniezione sicura nel template;
     * se `highlight_string` dovesse restituire false, normalizziamo a stringa vuota.
     */
    public function highlightString(string $code, bool $return = true): string
    {
        // We always request return=true for safe string handling
        return highlight_string($code, $return) ?: '';
    }
}
