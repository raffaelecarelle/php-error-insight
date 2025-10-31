<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests;

use PhpErrorInsight\Config;
use PhpErrorInsight\Internal\Model\Explanation;
use PhpErrorInsight\Internal\Renderer;
use PHPUnit\Framework\TestCase;

use function dirname;

final class RendererLinkingTest extends TestCase
{
    private function normalize(string $p): string
    {
        return preg_replace('#/{2,}#', '/', str_replace('\\', '/', $p)) ?? $p;
    }

    public function testHtmlContainsEditorLinksAndCopyButtonsWithRelativePaths(): void
    {
        $absFile = __FILE__;
        $line = __LINE__ - 2; // approximate reference within this test method
        $projectRoot = dirname(__DIR__); // repo root

        $explanation = Explanation::fromArray([
            'title' => 'Linking Test',
            'summary' => 'check links',
            'severityLabel' => 'Error',
            'original' => [
                'message' => 'Origin message',
                'file' => $absFile,
                'line' => $line,
            ],
            'trace' => [
                [
                    'function' => 'foo',
                    'class' => 'Bar',
                    'type' => '::',
                    'file' => $absFile,
                    'line' => $line,
                ],
                [
                    // simulate a path outside root but containing /vendor/
                    'function' => 'baz',
                    'class' => 'Qux',
                    'type' => '->',
                    'file' => '/some/other/vendor/pkg/src/File.php',
                    'line' => 99,
                ],
            ],
        ]);

        $cfg = new Config([
            'output' => Config::OUTPUT_HTML,
            'language' => 'en',
            'projectRoot' => $projectRoot,
            'editorUrl' => 'vscode://file/%file:%line',
        ]);

        $renderer = new Renderer();
        ob_start();
        $renderer->render($explanation, $cfg, 'error', false);
        $out = (string) ob_get_clean();

        $this->assertNotSame('', $out);

        // Expect relative path for this file
        $rel = $this->normalize(str_replace($this->normalize($projectRoot) . '/', '', $this->normalize($absFile)));
        $this->assertStringContainsString($rel . ':' . $line, $out, 'Stack line should contain rel:line');

        // Expect editor link with absolute path
        $href = 'vscode://file/' . $this->normalize($absFile) . ':' . $line;
        $this->assertStringContainsString('href="' . $href . '"', $out);

        // Expect per-row copy button for origin or first frame with data-copy="rel:line"
        $this->assertStringContainsString('data-copy="' . $rel . ':' . $line . '"', $out);

        // Vendor fallback for the simulated external frame
        $this->assertStringContainsString('vendor/pkg/src/File.php:99', $out);
    }
}
