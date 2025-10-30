<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Model;

use function is_array;
use function is_string;

final class Explanation
{
    /**
     * @param array{message?:string,file?:string,line?:int}|array{} $original
     * @param list<string>                                          $suggestions
     */
    public function __construct(public readonly array $original, public readonly array $suggestions, public readonly string $severityLabel, public readonly string $title, public readonly string $details, public readonly Trace $trace)
    {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $original = isset($data['original']) && is_array($data['original']) ? $data['original'] : [];
        $title = isset($original['message']) && is_string($original['message']) && '' !== $original['message']
            ? $original['message']
            : (string) ($data['title'] ?? '');

        $traceArr = isset($data['trace']) && is_array($data['trace']) ? $data['trace'] : [];

        return new self(
            $original,
            isset($data['suggestions']) && is_array($data['suggestions']) ? $data['suggestions'] : [],
            (string) ($data['severityLabel'] ?? 'Error'),
            $title,
            (string) ($data['details'] ?? ''),
            Trace::fromArray($traceArr),
        );
    }

    public function file(): string
    {
        return $this->original['file'] ?? '';
    }

    public function line(): int
    {
        return $this->original['line'] ?? 0;
    }

    public function message(): string
    {
        return $this->original['message'] ?? '';
    }
}
