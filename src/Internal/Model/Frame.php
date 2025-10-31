<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Model;

use JsonSerializable;

use function is_array;

final class Frame implements JsonSerializable
{
    public function __construct(
        public readonly ?string $file,
        public readonly ?int $line,
        public readonly ?string $class,
        public readonly ?string $type,
        public readonly ?string $function,
        /** @var array<int,mixed> */
        public readonly array $args = [],
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['file']) ? (string) $data['file'] : null,
            isset($data['line']) ? (int) $data['line'] : null,
            isset($data['class']) ? (string) $data['class'] : null,
            isset($data['type']) ? (string) $data['type'] : null,
            isset($data['function']) ? (string) $data['function'] : null,
            isset($data['args']) && is_array($data['args']) ? $data['args'] : [],
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'file' => $this->file ?? null,
            'line' => $this->line ?? null,
            'class' => $this->class ?? null,
            'type' => $this->type ?? null,
            'function' => $this->function ?? null,
            'args' => $this->args ?? [],
        ];
    }

    public function signature(): string
    {
        $cls = $this->class ?? '';
        $type = $this->type ?? '';
        $fn = $this->function ?? 'unknown';

        return trim($cls . $type . $fn . '()');
    }

    public function location(): string
    {
        $ff = $this->file ?? '';
        $ll = $this->line ?? 0;

        return '' !== $ff ? ($ff . (0 !== $ll ? ':' . $ll : '')) : '';
    }
}
