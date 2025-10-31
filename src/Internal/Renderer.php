<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\RendererFactoryInterface;
use PhpErrorInsight\Contracts\RendererInterface;
use PhpErrorInsight\Internal\Adapter\Render\Factory\RendererFactory;
use PhpErrorInsight\Internal\Model\Explanation;
use PhpErrorInsight\Internal\Util\EnvUtil;
use PhpErrorInsight\Internal\Util\HttpClientUtil;

/**
 * Renders error explanations across multiple formats (HTML, text, JSON).
 *
 * Why separate rendering:
 * - Presentation concerns change more frequently than domain logic; isolating them reduces ripple effects.
 * - Different environments (CLI vs web) require different defaults and headers.
 */
final class Renderer implements RendererInterface
{
    /**
     * High-level renderer: delegates all PHP core function calls to small util services.
     * Why: improves testability and keeps this class focused on presentation logic.
     */
    public function __construct(
        private readonly RendererFactoryInterface $rendererFactory = new RendererFactory(),
        private readonly EnvUtil $env = new EnvUtil(),
        private readonly HttpClientUtil $httpClient = new HttpClientUtil(),
    ) {
    }

    /**
     * Decide output format at runtime and delegate to specialized renderers.
     *
     * Why AUTO: we prefer text in CLI for readability and HTML in web for rich context.
     *
     * Also: if the incoming HTTP request declares a JSON content type (e.g. application/json,
     * application/ld+json, application/json-patch+json), we force JSON output regardless of config
     * so API clients always get machine-readable errors.
     */
    public function render(Explanation $explanation, Config $config, string $kind, bool $isShutdown): void
    {
        $format = $config->output ?? Config::OUTPUT_AUTO;

        // Force JSON output for JSON HTTP content types
        if (Config::OUTPUT_AUTO === $format) {
            $format = $this->env->isCliLike() ? Config::OUTPUT_TEXT : Config::OUTPUT_HTML;
            if ($this->httpClient->isHttpJsonRequest()) {
                $format = Config::OUTPUT_JSON;
            }
        }

        $this->rendererFactory->make($format)->render($explanation, $config, $kind, $isShutdown);
    }
}
