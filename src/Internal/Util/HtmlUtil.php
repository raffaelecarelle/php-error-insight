<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use const ENT_QUOTES;

final class HtmlUtil
{
    /**
     * Esegue l'escape HTML di una stringa.
     * Perché: `htmlspecialchars()` protegge da injection XSS; usiamo `ENT_QUOTES` per includere
     * sia apici singoli che doppi e impostiamo l'encoding (UTF-8) per evitare ambiguità.
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
