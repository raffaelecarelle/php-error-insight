<?php

declare(strict_types=1);

namespace ErrorExplainer\Contracts;

use ErrorExplainer\Config;

interface ExplainerInterface
{
    /**
     * Build an educational explanation based on the given error/exception data.
     * Returns an associative array with keys: title, summary, details, suggestions, severityLabel, original.
     *
     * @param string                              $kind  'error'|'exception'|'shutdown'
     * @param array<int,array<string,mixed>>|null $trace
     *
     * @return array<string,mixed>
     */
    public function explain(string $kind, string $message, ?string $file, ?int $line, ?array $trace, ?int $severity, Config $config): array;
}
