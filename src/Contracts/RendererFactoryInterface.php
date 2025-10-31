<?php

declare(strict_types=1);

namespace PhpErrorInsight\Contracts;

interface RendererFactoryInterface
{
    public function make(string $format): RendererInterface;
}
