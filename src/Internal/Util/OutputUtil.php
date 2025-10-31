<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

final class OutputUtil
{
    /**
     * Writes a string to standard output without a newline.
     * Why: encapsulating `echo` allows capturing output in tests and keeps a
     * consistent emission strategy (stdout).
     */
    public function write(string $s): void
    {
        echo $s;
    }

    /**
     * Writes a string followed by a newline to standard output.
     * Why: having a dedicated helper avoids manual concatenations and end-of-line differences.
     */
    public function writeln(string $s = ''): void
    {
        echo $s, "\n";
    }
}
