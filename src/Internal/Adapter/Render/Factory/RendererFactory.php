<?php

declare(strict_types=1);

namespace PhpErrorInsight\Internal\Adapter\Render\Factory;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\RendererFactoryInterface;
use PhpErrorInsight\Contracts\RendererInterface;
use PhpErrorInsight\Internal\Adapter\Render\CliRendererAdapter;
use PhpErrorInsight\Internal\Adapter\Render\HtmlRendererAdapter;
use PhpErrorInsight\Internal\Adapter\Render\JsonRendererAdapter;

class RendererFactory implements RendererFactoryInterface
{
    public function make(string $format): RendererInterface
    {
        if (Config::OUTPUT_JSON === $format) {
            return new JsonRendererAdapter();
        }

        if (Config::OUTPUT_TEXT === $format) {
            return new CliRendererAdapter();
        }

        return new HtmlRendererAdapter();
    }
}
