<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Adapter\AI;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\AIClientInterface;
use PhpErrorInsight\Internal\Util\HttpClientUtil;

use function is_array;
use function is_string;

class OpenAiClientAdapter implements AIClientInterface
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

        $url = null !== $config->apiUrl && '' !== $config->apiUrl && '0' !== $config->apiUrl ? $config->apiUrl : 'https://api.openai.com/v1/chat/completions';
        $headers = [
            'Authorization: Bearer ' . $config->apiKey,
        ];
        $payload = [
            'model' => $config->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an assistant that explains PHP errors in an educational and concise way.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.2,
        ];
        $resp = $this->httpClientUtil->requestJson('POST', $url, $payload, $headers, 12);
        if (!is_array($resp)) {
            return null;
        }

        if (isset($resp['choices'][0]['message']['content']) && is_string($resp['choices'][0]['message']['content'])) {
            return trim($resp['choices'][0]['message']['content']);
        }

        if (isset($resp['error']['message']) && is_string($resp['error']['message'])) {
            return null; // avoid exposing API errors to end-user
        }

        return null;
    }
}
