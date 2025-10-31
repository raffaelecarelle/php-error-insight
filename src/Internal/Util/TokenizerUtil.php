<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Util;

final class TokenizerUtil
{
    /** @return array<int, mixed> */
    public function tokenize(string $source): array
    {
        return token_get_all($source);
    }
}
