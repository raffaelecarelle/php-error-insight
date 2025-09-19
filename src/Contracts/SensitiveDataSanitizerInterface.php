<?php

declare(strict_types=1);

namespace ErrorExplainer\Contracts;

use ErrorExplainer\Internal\SanitizerConfig;

/**
 * Simple sensitive data sanitizer used before sending prompts to AI providers.
 * Rules are intentionally conservative and configurable through SanitizerConfig.
 */
interface SensitiveDataSanitizerInterface
{
    public function sanitize(string $text, SanitizerConfig $config): string;
}
