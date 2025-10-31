<?php

declare(strict_types=1);

namespace PhpErrorInsight\Contracts;

use PhpErrorInsight\Config;
use PhpErrorInsight\Internal\Model\Explanation;

interface RendererInterface
{
    /**
     * Render the explanation according to configuration and environment.
     *
     * @param string $kind 'error'|'exception'|'shutdown'
     */
    public function render(Explanation $explanation, Config $config, string $kind, bool $isShutdown): void;
}
