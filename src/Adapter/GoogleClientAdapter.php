<?php

declare(strict_types=1);

namespace PhpErrorInsight\Adapter;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\AIClientInterface;
use PhpErrorInsight\Internal\Util\HttpClientUtil;

use function is_array;
use function is_string;

class GoogleClientAdapter implements AIClientInterface
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

        $base = null !== $config->apiUrl && '' !== $config->apiUrl && '0' !== $config->apiUrl ? $config->apiUrl : 'https://generativelanguage.googleapis.com/v1/models';
        $url = rtrim($base, '/') . '/' . rawurlencode($config->model) . ':generateContent?key=' . urlencode($config->apiKey);
        $headers = [];
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
            ],
        ];
        $resp = $this->httpClientUtil->requestJson('POST', $url, $payload, $headers, 12);
        if (!is_array($resp)) {
            return null;
        }

        // Gemini API response: candidates[0].content.parts[0].text
        if (isset($resp['candidates'][0]['content']['parts'][0]['text']) && is_string($resp['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($resp['candidates'][0]['content']['parts'][0]['text']);
        }

        // Some responses may place text at candidates[0].output_text (older beta)
        if (isset($resp['candidates'][0]['output_text']) && is_string($resp['candidates'][0]['output_text'])) {
            return trim($resp['candidates'][0]['output_text']);
        }

        return null;
    }
}
