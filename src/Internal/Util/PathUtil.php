<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use Symfony\Component\Filesystem\Path;

use function dirname;
use function strlen;

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
     * Resolves the real path on the filesystem, leaving the input unchanged if it fails.
     * Why: `realpath()` can return false when the file does not exist; the fallback preserves
     * the original information, avoiding propagation of `false`.
     */
    public function real(string $path): string
    {
        $rp = realpath($path);

        return false !== $rp ? $rp : $path;
    }

    public function makeRelative(string $path, string $projectRoot): string
    {
        return Path::makeRelative($path, $projectRoot);
    }

    public function toEditorHref(string $editorTpl, string $file, int $line, string $projectRoot, string $hostProjectRoot): string
    {
        $file = $this->normalizeSlashes($file);
        $pr = $this->normalizeSlashes($projectRoot);
        $hr = $this->normalizeSlashes($hostProjectRoot);
        if ('' !== $hr && '' !== $pr && str_starts_with($file, $pr . '/')) {
            $file = $hr . substr($file, strlen($pr));
        }

        $search = ['%file', '%line'];
        $replace = [$file, $line];

        return str_replace($search, $replace, $editorTpl);
    }

    /**
     * Removes any trailing directory separator in a portable way.
     * Why: normalizing paths avoids double separators when concatenating segments.
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
