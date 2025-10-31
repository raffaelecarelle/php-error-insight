<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Adapter\AI\Factory;

use InvalidArgumentException;
use PhpErrorInsight\Contracts\AIAdapterFactoryInterface;
use PhpErrorInsight\Contracts\AIClientInterface;
use PhpErrorInsight\Internal\Adapter\AI\AnthropicClientAdapter;
use PhpErrorInsight\Internal\Adapter\AI\GoogleClientAdapter;
use PhpErrorInsight\Internal\Adapter\AI\LocalClientAdapter;
use PhpErrorInsight\Internal\Adapter\AI\OpenAiClientAdapter;

class AIAdapterFactory implements AIAdapterFactoryInterface
{
    public function make(string $backend): AIClientInterface
    {
        if ('local' === $backend) {
            return new LocalClientAdapter();
        }

        if ('api' === $backend || 'openai' === $backend) {
            return new OpenAiClientAdapter();
        }

        if ('anthropic' === $backend) {
            return new AnthropicClientAdapter();
        }

        if ('google' === $backend || 'gemini' === $backend) {
            return new GoogleClientAdapter();
        }

        return throw new InvalidArgumentException('Unknown backend: ' . $backend);
    }
}
