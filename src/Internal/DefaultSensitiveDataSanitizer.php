<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal;

use PhpErrorInsight\Contracts\SensitiveDataSanitizerInterface;

use function in_array;

/**
 * Best-effort sensitive data masker for logs and rendered output.
 *
 * Why conservative rules: false positives harm debugging more than partial masking. Rules can be customized
 * via SanitizerConfig allowing teams to tune for their domain.
 */
final class DefaultSensitiveDataSanitizer implements SensitiveDataSanitizerInterface
{
    /**
     * Apply masking rules in a predictable order to reduce cross-rule interference.
     */
    public function sanitize(string $text, SanitizerConfig $config): string
    {
        if ('' === $text) {
            return '';
        }

        $out = $text;

        // 1) Apply user custom regex first (highest priority)
        foreach ($config->customRegex as $rx => $replacement) {
            $out = preg_replace($rx, $replacement, $out) ?? $out;
        }

        // 2) Secrets and credentials
        if (in_array('secrets', $config->enabledRules, true)) {
            $out = preg_replace('/(Authorization:?\s*)(Bearer|Basic)(\s+)[A-Za-z0-9\-_.=:+\/]+/i', '$1$2$3' . $config->masks['default'], $out) ?? $out;
            // JWT like strings
            $out = preg_replace('/\beyJ[0-9A-Za-z_\-]+\.[0-9A-Za-z_\-]+\.[0-9A-Za-z_\-]+\b/', $config->masks['default'], $out) ?? $out;
        }

        // 3) Payment (moved before PII to avoid conflicts)
        if (in_array('payment', $config->enabledRules, true)) {
            // Credit card regex - matches 13-19 digits with optional spaces/dashes but not phone-like patterns
            $out = preg_replace('/\b(?:\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{1,7}|\d{13,19})\b/', $config->masks['default'], $out) ?? $out;
        }

        // 4) PII
        if (in_array('pii', $config->enabledRules, true)) {
            $out = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $config->masks['email'] ?? $config->masks['default'], $out) ?? $out;
            // More specific phone regex to avoid false positives with credit cards
            // Only match phone patterns that start with + or have clear phone formatting
            $out = preg_replace('/\+\d{1,4}[\s\-]?\d{2,4}[\s\-]?\d{3,4}[\s\-]?\d{3,4}\b/', $config->masks['phone'] ?? $config->masks['default'], $out) ?? $out;
            $out = preg_replace('/[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]/i', $config->masks['default'], $out) ?? $out; // Italian CF simplified
            $out = preg_replace('/[A-Z]{2}\d{2}[A-Z0-9]{11,30}/', $config->masks['default'], $out) ?? $out; // IBAN generic
        }

        // 5) Network/metadata
        if (in_array('network', $config->enabledRules, true)) {
            // Fully redact private IPv4 ranges: 10.0.0.0/8, 192.168.0.0/16, 172.16.0.0/12
            $out = preg_replace('/\b(?:10\.(?:\d{1,3}\.){2}\d{1,3}|192\.168\.(?:\d{1,3}\.)\d{1,3}|172\.(?:1[6-9]|2\d|3[0-1])\.(?:\d{1,3}\.)\d{1,3})\b/', $config->masks['default'], $out) ?? $out;
            $out = preg_replace('/(Cookie:).*?(\r?\n)/i', '$1 ' . $config->masks['default'] . '$2', $out) ?? $out;
        }

        return $out;
    }
}
