<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

final class OutputUtil
{
    /**
     * Scrive una stringa su output standard senza newline.
     * Perché: incapsulare `echo` permette di intercettare l'output nei test e di
     * mantenere coerente la strategia di emissione (stdout).
     */
    public function write(string $s): void
    {
        echo $s;
    }

    /**
     * Scrive una stringa seguita da newline su output standard.
     * Perché: avere un helper dedicato evita concatenazioni manuali e differenze di fine riga.
     */
    public function writeln(string $s = ''): void
    {
        echo $s, "\n";
    }
}
