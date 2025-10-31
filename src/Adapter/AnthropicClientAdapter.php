<?php

declare(strict_types=1);

namespace PhpErrorInsight\Adapter;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\AIClientInterface;
use PhpErrorInsight\Internal\Util\HttpClientUtil;

use function is_array;
use function is_string;

class AnthropicClientAdapter implements AIClientInterface
{
    public function __construct(
        private readonly HttpClientUtil $httpClientUtil = new HttpClientUtil(),
    ) {
    }

    public function generateExplanation(string $prompt, Config $config): ?string
    {
        if (null === $config->apiKey || '' === $config->apiKey || '0' === $config->apiKey || (null === $config->model || '' === $config->model || '0' === $config->model)) {
            return null;
        }

        $url = null !== $config->apiUrl && '' !== $config->apiUrl && '0' !== $config->apiUrl ? $config->apiUrl : 'https://api.anthropic.com/v1/messages';
        $headers = [
            'x-api-key: ' . $config->apiKey,
            'anthropic-version: 2023-06-01',
        ];
        $payload = [
            'model' => $config->model,
            'max_tokens' => 400,
            'messages' => [
                ['role' => 'user', 'content' => [['type' => 'text', 'text' => $prompt]]],
            ],
            'system' => 'You are an assistant that explains PHP errors in an educational and concise way.',
            'temperature' => 0.2,
        ];
        $resp = $this->httpClientUtil->requestJson('POST', $url, $payload, $headers, 12);
        if (!is_array($resp)) {
            return null;
        }

        // Anthropic Messages API: content is array of blocks with type=text
        if (isset($resp['content'][0]['text']) && is_string($resp['content'][0]['text'])) {
            return trim($resp['content'][0]['text']);
        }

        // Some SDKs nest under content[0]['type' => 'text']
        if (isset($resp['content'][0]) && is_array($resp['content'][0]) && isset($resp['content'][0]['type']) && 'text' === $resp['content'][0]['type'] && isset($resp['content'][0]['text'])) {
            return trim((string) $resp['content'][0]['text']);
        }

        return null;
    }
}
