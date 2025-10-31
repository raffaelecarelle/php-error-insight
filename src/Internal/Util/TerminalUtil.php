<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

use function function_exists;
use function in_array;

use const STDOUT;

final class TerminalUtil
{
    public function __construct(
        private readonly EnvUtil $env = new EnvUtil(),
        private readonly StringUtil $str = new StringUtil(),
    ) {
    }

    public function supportsAnsi(): bool
    {
        if (!$this->env->isCliLike()) {
            return false;
        }

        if ('' !== $this->env->getEnv('NO_COLOR')) {
            return false;
        }

        $force = $this->env->getEnv('FORCE_COLOR');
        if ('' !== $force && in_array($this->str->toLower($force), ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        // Check TTY if possible
        if (function_exists('stream_isatty')) {
            /* @noinspection PhpComposerExtensionStubsInspection */
            return @stream_isatty(STDOUT);
        }

        if (function_exists('posix_isatty')) {
            /* @noinspection PhpComposerExtensionStubsInspection */
            return @posix_isatty(STDOUT);
        }

        // Fall back to TERM
        $term = $this->env->getEnv('TERM');

        return '' !== $term && 'dumb' !== $this->str->toLower($term);
    }
}
