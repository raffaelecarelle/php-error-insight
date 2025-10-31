<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Adapter\AI;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\AIClientInterface;
use PhpErrorInsight\Internal\Util\HttpClientUtil;

use function is_array;
use function is_string;

class LocalClientAdapter implements AIClientInterface
{
    public function __construct(
        private readonly HttpClientUtil $httpClientUtil = new HttpClientUtil(),
    ) {
    }

    public function generateExplanation(string $prompt, Config $config): ?string
    {
        if (null === $config->model || '' === $config->model || '0' === $config->model) {
            return null;
        }

        $base = null !== $config->apiUrl && '' !== $config->apiUrl && '0' !== $config->apiUrl ? $config->apiUrl : 'http://localhost:11434'; // Ollama default
        $url = rtrim($base, '/') . '/api/generate';
        $payload = [
            'model' => $config->model,
            'prompt' => $prompt,
            'stream' => false,
            // You can tweak options like temperature here if backend supports it
        ];
        $resp = $this->httpClientUtil->requestJson('POST', $url, $payload, [], 10);
        if (!is_array($resp)) {
            return null;
        }

        // Ollama returns { response: string, ... }
        if (isset($resp['response']) && is_string($resp['response'])) {
            return trim($resp['response']);
        }

        // LocalAI OpenAI-compatible servers might be used under 'local' if apiUrl points to /v1
        if (isset($resp['choices'][0]['message']['content']) && is_string($resp['choices'][0]['message']['content'])) {
            return trim($resp['choices'][0]['message']['content']);
        }

        return null;
    }
}
