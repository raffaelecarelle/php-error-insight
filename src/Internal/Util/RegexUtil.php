<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function is_string;

final class RegexUtil
{
    /**
     * Sostituisce tramite espressione regolare e garantisce una stringa in uscita.
     * Perché: `preg_replace()` può restituire null in caso di errore; conserviamo la stringa
     * originale come fallback per evitare di propagare null e semplificare i chiamanti.
     */
    public function replace(string $pattern, string $replacement, string $subject): string
    {
        $out = preg_replace($pattern, $replacement, $subject);

        return is_string($out) ? $out : $subject;
    }
}
