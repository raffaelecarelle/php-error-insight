<?php

declare(strict_types=1);

namespace ErrorExplainer\Contracts;

use ErrorExplainer\Config;

interface AIClientInterface
{
    /**
     * Generate an explanation text for a prepared prompt.
     */
    public function generateExplanation(string $prompt, Config $config): ?string;
}
