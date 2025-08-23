<?php

declare(strict_types=1);

namespace ErrorExplainer\Contracts;

use ErrorExplainer\Config;

interface RendererInterface
{
    /**
     * Render the explanation according to configuration and environment.
     *
     * @param array<string,mixed> $explanation
     * @param Config $config
     * @param string $kind 'error'|'exception'|'shutdown'
     * @param bool $isShutdown
     */
    public function render(array $explanation, Config $config, string $kind, bool $isShutdown): void;
}
