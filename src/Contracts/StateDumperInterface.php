<?php

declare(strict_types=1);

namespace ErrorExplainer\Contracts;

/**
 * Abstraction for collecting extended runtime state during error handling.
 */
interface StateDumperInterface
{
    /**
     * Collect extended state information at the time of an error/exception.
     *
     * @param array<int, array<string, mixed>>|null $traceFromHandler a debug_backtrace()-like array
     *
     * @return array<string, mixed>
     */
    public function collectState(?array $traceFromHandler = null): array;
}
