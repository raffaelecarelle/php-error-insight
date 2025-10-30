<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Model;

final class Explanation
{
    /** @var array{message?:string,file?:string,line?:int}|array{} */
    public readonly array $original;
    /** @var list<string> */
    public readonly array $suggestions;
    public readonly string $severityLabel;
    public readonly string $title;
    public readonly string $details;
    public readonly Trace $trace;

    /**
     * @param array{message?:string,file?:string,line?:int}|array{} $original
     * @param list<string>                                          $suggestions
     */
    public function __construct(
        array $original,
        array $suggestions,
        string $severityLabel,
        string $title,
        string $details,
        Trace $trace,
    ) {
        $this->original = $original;
        $this->suggestions = $suggestions;
        $this->severityLabel = $severityLabel;
        $this->title = $title;
        $this->details = $details;
        $this->trace = $trace;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $original = isset($data['original']) && is_array($data['original']) ? $data['original'] : [];
        $title = isset($original['message']) && is_string($original['message']) && $original['message'] !== ''
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
        return isset($this->original['file']) ? (string) $this->original['file'] : '';
    }

    public function line(): int
    {
        return isset($this->original['line']) ? (int) $this->original['line'] : 0;
    }

    public function message(): string
    {
        return isset($this->original['message']) ? (string) $this->original['message'] : '';
    }
}
