<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function function_exists;

final class TerminalUtil
{
    public function streamIsatty(mixed $stream): bool
    {
        if (function_exists('stream_isatty')) {
            /* @noinspection PhpComposerExtensionStubsInspection */
            return @stream_isatty($stream);
        }

        return false;
    }

    public function posixIsatty(mixed $stream): bool
    {
        if (function_exists('posix_isatty')) {
            /* @noinspection PhpComposerExtensionStubsInspection */
            return @posix_isatty($stream);
        }

        return false;
    }
}
