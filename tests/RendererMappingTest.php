<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests;

use PhpErrorInsight\Config;
use PhpErrorInsight\Internal\Model\Explanation;
use PhpErrorInsight\Internal\Renderer;
use PHPUnit\Framework\TestCase;

final class RendererMappingTest extends TestCase
{
    public function testEditorHrefMapsContainerPathToHostPath(): void
    {
        $config = new Config([
            'projectRoot' => '/container/app',
            'hostProjectRoot' => '/host/app',
            'editorUrl' => 'vscode://file/%file:%line',
            'output' => 'html',
        ]);

        $explanation = Explanation::fromArray([
            'title' => 'Test Error',
            'original' => [
                'message' => 'Boom',
                'file' => '/container/app/src/Foo.php',
                'line' => 10,
            ],
            'trace' => [],
        ]);

        $renderer = new Renderer();

        ob_start();
        $renderer->render($explanation, $config, 'error', false);
        $html = (string) ob_get_clean();

        // Expect the editor link to use the host project root mapping
        $this->assertStringContainsString('href="vscode://file//host/app/src/Foo.php:10"', $html);
    }
}
