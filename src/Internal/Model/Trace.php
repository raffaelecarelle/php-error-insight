<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Model;

/**
 * @phpstan-type FrameArray array<string,mixed>
 */
final class Trace
{
    /**
     * @param list<Frame> $frames
     */
    public function __construct(public readonly array $frames)
    {
    }

    /**
     * @param array<int, array<string,mixed>> $data
     */
    public static function fromArray(array $data): self
    {
        $frames = [];
        foreach ($data as $f) {
            $frames[] = Frame::fromArray($f);
        }

        return new self($frames);
    }
}
