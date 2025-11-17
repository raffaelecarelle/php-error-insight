<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use SensitiveParameter;
use Throwable;

use function is_array;
use function is_object;
use function is_string;

/**
 * Sanitizes sensitive parameters marked with #[SensitiveParameter] attribute.
 * Replaces their values with a mask string to prevent leakage in error pages and AI prompts.
 */
final class SensitiveParameterSanitizer
{
    public function __construct(private readonly string $mask = '***MASKED***')
    {
    }

    /**
     * Sanitize function/method arguments based on their parameter definitions.
     *
     * @param array<int,mixed> $args
     *
     * @return array<int,mixed>
     */
    public function sanitizeArgs(string $class, string $type, string $function, array $args): array
    {
        if ([] === $args) {
            return $args;
        }

        try {
            $reflection = $this->getReflection($class, $type, $function);
            if (null === $reflection) {
                return $args;
            }

            $params = $reflection->getParameters();
            $sanitized = [];

            foreach ($args as $index => $value) {
                $param = $params[$index] ?? null;
                $sanitized[] = null !== $param && $this->hasSensitiveAttribute($param) ? $this->mask : $value;
            }

            return $sanitized;
        } catch (Throwable) {
            // If reflection fails, return original args
            return $args;
        }
    }

    /**
     * Recursively sanitize arrays and objects that may contain sensitive data.
     */
    public function sanitizeRecursive(mixed $data): mixed
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitized[$key] = $this->sanitizeRecursive($value);
            }

            return $sanitized;
        }

        if (is_object($data)) {
            // For objects, we just return a simple representation
            return '(object ' . $data::class . ')';
        }

        return $data;
    }

    /**
     * Sanitize a text string for AI prompt by masking common sensitive patterns.
     */
    public function sanitizeText(string $text): string
    {
        // Mask Authorization headers
        $text = preg_replace('/Authorization:\s*Bearer\s+[^\s]+/i', 'Authorization: Bearer ' . $this->mask, $text);

        // Mask JWT-like tokens
        $text = preg_replace('/[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}/', $this->mask, (string) $text);

        // Mask email addresses
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $this->mask, (string) $text);

        // Mask common API key patterns
        $text = preg_replace('/(api[_-]?key|token|secret|password)[\s:="\']*[a-zA-Z0-9_\-]{16,}/i', '$1=' . $this->mask, (string) $text);

        return is_string($text) ? $text : '';
    }

    /**
     * Get reflection for function or method.
     */
    private function getReflection(?string $class, ?string $type, ?string $function): ReflectionFunction|ReflectionMethod|null
    {
        if (null === $function || '' === $function) {
            return null;
        }

        // Method call
        if (null !== $class && '' !== $class && null !== $type && '' !== $type) {
            try {
                return new ReflectionMethod($class, $function);
            } catch (Throwable) {
                return null;
            }
        }

        // Function call
        try {
            return new ReflectionFunction($function);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Check if parameter has #[SensitiveParameter] attribute.
     */
    private function hasSensitiveAttribute(ReflectionParameter $param): bool
    {
        $attributes = $param->getAttributes(SensitiveParameter::class);

        return [] !== $attributes;
    }
}
