<?php

declare(strict_types=1);

namespace PhpErrorInsight\Contracts;

use PhpErrorInsight\Config;

interface AIClientInterface
{
    /**
     * Generate an explanation text for a prepared prompt.
     */
    public function generateExplanation(string $prompt, Config $config): ?string;
}
