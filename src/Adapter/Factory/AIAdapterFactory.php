<?php

declare(strict_types=1);

namespace PhpErrorInsight\Adapter\Factory;

use InvalidArgumentException;
use PhpErrorInsight\Adapter\AnthropicClientAdapter;
use PhpErrorInsight\Adapter\GoogleClientAdapter;
use PhpErrorInsight\Adapter\LocalClientAdapter;
use PhpErrorInsight\Adapter\OpenAiClientAdapter;
use PhpErrorInsight\Contracts\AIAdapterFactoryInterface;
use PhpErrorInsight\Contracts\AIClientInterface;

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
