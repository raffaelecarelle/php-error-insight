<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal;

use PhpErrorInsight\Adapter\Factory\AIAdapterFactory;
use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\AIAdapterFactoryInterface;
use PhpErrorInsight\Contracts\ExplainerInterface;
use PhpErrorInsight\Internal\Model\Explanation;

/**
 * Produces human-friendly explanations for runtime problems and optionally enriches them via an AI backend.
 *
 * Why separate from rendering and handling:
 * - Keeps the knowledge/heuristics about errors isolated and testable.
 * - Allows swapping the AI provider without changing how we render or capture errors.
 */
final class Explainer implements ExplainerInterface
{
    public function __construct(
        private readonly AIAdapterFactoryInterface $adapterFactory = new AIAdapterFactory(),
        private readonly Util\JsonUtil $json = new Util\JsonUtil(),
        private readonly Util\TypeUtil $type = new Util\TypeUtil()
    ) {
    }

    /**
     * Build an educational explanation based on the given error/exception data.
     *
     * Why we attach both normalized trace and minimal globals:
     * - Normalized trace enables consistent presentation across formats.
     * - Minimal globals snapshot helps reproducing issues without leaking too much data.
     *
     * Returns an associative array with keys: title, summary, details, suggestions, severityLabel, original.
     *
     * @param string                              $kind  'error'|'exception'|'shutdown'
     * @param array<int,array<string,mixed>>|null $trace
     *
     * @return array<string,mixed>
     */
    public function explain(string $kind, string $message, ?string $file, ?int $line, ?array $trace, ?int $severity, Config $config): array
    {
        $explanation = Explanation::make(Translator::t($config, 'title.basic'), $severity, $message, $file, $line, $trace);

        if ('none' !== $config->backend) {
            $aiText = $this->aiExplain($message, $file, $line, $severity, $config);

            if (null === $aiText) {
                return $explanation->toArray();
            }

            $aiTextDecoded = $this->json->decodeObject($aiText);

            if (null === $aiTextDecoded) {
                return $explanation->toArray();
            }

            if ($this->type->isArray($aiTextDecoded)) {
                $explanation = Explanation::make(Translator::t($config, 'title.ai'), $severity, $message, $file, $line, $trace, $aiTextDecoded['suggestions'] ?? [], $aiTextDecoded['details'] ?? '');
            }
        }

        return $explanation->toArray();
    }

    private function aiExplain(string $message, ?string $file, ?int $line, ?int $severity, Config $config): ?string
    {
        $lang = '' !== $config->language && '0' !== $config->language ? $config->language : 'en';
        $where = (null !== $file ? ($file . (null !== $line ? ":{$line}" : '')) : Translator::t($config, 'details.unknown'));
        $prompt = "You are an assistant that explains PHP errors in {$lang}.
Message: {$message}
Severity: {$severity}
Location: {$where}
Provide a concise Details (max 500 chars). Then list up to 2 practical fix suggestions.
Output must be in pure json (can be used with json_decode php function)): {\"details\": \"\", \"suggestions\": [\"\", \"\"]}";

        $backend = strtolower(trim($config->backend));

        return $this->adapterFactory->make($backend)->generateExplanation($prompt, $config);
    }
}
