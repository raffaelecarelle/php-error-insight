<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

final class MathUtil
{
    /**
     * Returns the maximum of two integers using the native function.
     * Why: delegating to `max()` ensures correctness and performance without reinventing logic.
     */
    public function max(int $a, int $b): int
    {
        return max($a, $b);
    }

    /**
     * Returns the minimum of two integers using the native function.
     * Why: using `min()` avoids manual conditional branches and keeps code clear/testable.
     */
    public function min(int $a, int $b): int
    {
        return min($a, $b);
    }
}
