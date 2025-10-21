<?php

declare(strict_types=1);

namespace ErrorExplainer\Tests;

use ErrorExplainer\Config;
use ErrorExplainer\Internal\Renderer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class EditorPathMappingTest extends TestCase
{
    public function testEditorHrefUsesHostPathWhenMappingConfigured(): void
    {
        $config = new Config([
            'projectRoot' => '/app',
            'editorUrl' => 'vscode://file/%file:%line',
            'containerPath' => '/app',
            'hostPath' => '/Users/me/project',
        ]);

        $explanation = [
            'title' => 'Test',
            'severityLabel' => 'Error',
            'original' => [
                'message' => 'Boom',
                'file' => '/app/src/Foo.php',
                'line' => 42,
            ],
            'trace' => [
                [
                    'file' => '/app/src/Foo.php',
                    'line' => 42,
                    'function' => 'bar',
                    'class' => 'Foo',
                    'type' => '::',
                ],
            ],
        ];

        $renderer = new Renderer();
        $ref = new ReflectionClass($renderer);
        $m = $ref->getMethod('buildViewData');
        $m->setAccessible(true);
        /** @var array<string,mixed> $data */
        $data = $m->invoke($renderer, $explanation, $config);

        $this->assertIsArray($data['frames']);
        $this->assertNotEmpty($data['frames']);
        $first = $data['frames'][0];
        $this->assertArrayHasKey('editorHref', $first);
        $href = (string) $first['editorHref'];
        $this->assertStringContainsString('vscode://file/', $href);
        $this->assertStringContainsString('/Users/me/project/src/Foo.php:42', $href, 'Editor href should use host path');
    }
}
