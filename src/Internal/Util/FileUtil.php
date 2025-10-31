<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use Symfony\Component\Filesystem\Filesystem;

use function is_string;

use const FILE_IGNORE_NEW_LINES;

final class FileUtil
{
    /**
     * Checks whether a path points to an existing file, avoiding ambiguous falsy cases.
     * Why: values like empty string or '0' should not pass; we also wrap `is_file()`
     * to allow filesystem simulation in tests.
     */
    public function isFile(?string $path): bool
    {
        return null !== $path && is_file($path);
    }

    /**
     * Reads a file into an array of lines without line endings; returns [] on error.
     * Why: `file()` may emit warnings and return false; we use the silence operator and
     * normalize to an empty array for a simpler and more robust calling flow.
     *
     * @return array<int,string>
     */
    public function fileLines(string $path): array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES);

        return false === $lines ? [] : $lines;
    }

    /**
     * Restituisce il contenuto di un file o stringa vuota in caso di errore.
     * Perché: `file_get_contents()` può fallire; convertiamo a stringa sicura evitando warning,
     * così i consumatori non devono gestire `false` o eccezioni.
     */
    public function getContents(string $path): string
    {
        $s = @file_get_contents($path);

        return is_string($s) ? $s : '';
    }
}
