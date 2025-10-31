<?php

declare(strict_types=1);

namespace PhpErrorInsight\Contracts;

interface AIAdapterFactoryInterface
{
    public function make(string $backend): AIClientInterface;
}
