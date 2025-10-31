<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function dirname;

use const DIRECTORY_SEPARATOR;

final class PathUtil
{
    /**
     * Returns the parent directory at a given level.
     * Why: delegating to `dirname()` ensures cross-platform compatibility and edge-case handling.
     */
    public function dirName(string $path, int $levels = 1): string
    {
        return dirname($path, $levels);
    }

    /**
     * Risolve il percorso reale sul filesystem, lasciando invariato l'input se fallisce.
     * Perché: `realpath()` può tornare false quando il file non esiste; il fallback preserva
     * l'informazione originale evitando di propagare `false`.
     */
    public function real(string $path): string
    {
        $rp = realpath($path);

        return false !== $rp ? $rp : $path;
    }

    /**
     * Rimuove l'eventuale separatore finale in modo portabile.
     * Perché: normalizzare i percorsi evita doppi separatori quando si concatenano segmenti.
     */
    public function rtrimSep(string $path): string
    {
        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Normalizza gli slash a forward slash e comprime gli slash consecutivi.
     * Perché: uniformare il formato dei percorsi semplifica i confronti e migliora la portabilità
     * tra Windows e Unix-like.
     */
    public function normalizeSlashes(string $path): string
    {
        $p = str_replace(['\\'], '/', $path);

        return preg_replace('#/{2,}#', '/', $p) ?? $p;
    }
}
