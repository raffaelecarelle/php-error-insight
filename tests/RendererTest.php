<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests;

use PhpErrorInsight\Config;
use PhpErrorInsight\Internal\Model\Explanation;
use PhpErrorInsight\Internal\Renderer;
use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase
{
    private function baseExplanation(): Explanation
    {
        return Explanation::fromArray([
            'title' => 'PHP Error Explanation',
            'details' => 'More details here',
            'suggestions' => ['Try this', 'Then that'],
            'severityLabel' => 'Notice',
            'original' => [
                'message' => 'Test message',
                'file' => __FILE__,
                'line' => __LINE__,
            ],
            'trace' => [],
        ]);
    }

    public function testRenderTextOutputsSuggestions(): void
    {
        $renderer = new Renderer();
        $config = new Config(['output' => Config::OUTPUT_TEXT, 'language' => 'en', 'verbose' => true]);
        $exp = $this->baseExplanation();

        ob_start();
        $renderer->render($exp, $config, 'error', false);
        $out = (string) ob_get_clean();

        $this->assertStringContainsString('Suggestions:', $out);
        $this->assertStringContainsString('Try this', $out);
    }

    public function testRenderJsonOutputsJson(): void
    {
        $renderer = new Renderer();
        $config = new Config(['output' => Config::OUTPUT_JSON, 'language' => 'en']);
        $exp = $this->baseExplanation();

        ob_start();
        $renderer->render($exp, $config, 'error', false);
        $out = (string) ob_get_clean();

        $this->assertStringContainsString('"original"', $out);
        $this->assertStringContainsString('"Test message"', $out);
    }

    public function testRenderHtmlOutputsHtmlDocument(): void
    {
        $renderer = new Renderer();
        $config = new Config(['output' => Config::OUTPUT_HTML, 'language' => 'en']);
        $exp = $this->baseExplanation();

        ob_start();
        $renderer->render($exp, $config, 'error', false);
        $out = (string) ob_get_clean();

        $this->assertNotSame('', $out);
        $this->assertStringContainsString('<html', strtolower($out));
        $this->assertStringContainsString('test message', strtolower($out));
    }
}
