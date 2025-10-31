<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Adapter\Render;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\RendererInterface;
use PhpErrorInsight\Internal\Model\Explanation;
use PhpErrorInsight\Internal\Util\EnvUtil;
use PhpErrorInsight\Internal\Util\HttpUtil;
use PhpErrorInsight\Internal\Util\JsonUtil;
use PhpErrorInsight\Internal\Util\OutputUtil;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;

class JsonRendererAdapter implements RendererInterface
{
    public function __construct(
        private readonly JsonUtil $json = new JsonUtil(),
        private readonly HttpUtil $http = new HttpUtil(),
        private readonly OutputUtil $out = new OutputUtil(),
        private readonly EnvUtil $env = new EnvUtil(),

    ) {
    }

    public function render(Explanation $explanation, Config $config, string $kind, bool $isShutdown): void
    {
        if (!$this->env->isCliLike() && !$this->http->headersSent()) {
            $this->sendJsonHeaders();
        }

        $json = $this->json->encode($explanation->jsonSerialize(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $suffix = $this->env->isCliLike() ? "\n" : '';
        $this->out->write($json . $suffix);
    }

    private function sendJsonHeaders(): void
    {
        $this->http->sendHeader('Content-Type: application/json; charset=utf-8');
        $this->http->setResponseCode(500);
    }
}
