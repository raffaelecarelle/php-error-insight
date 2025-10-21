<?php

declare(strict_types=1);

namespace ErrorExplainer\Tests;

use ErrorExplainer\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        // Cleanup env overrides after each test
        putenv('PHP_ERROR_INSIGHT_ROOT');
        putenv('PHP_ERROR_INSIGHT_EDITOR');
    }

    public function testFromEnvAndArrayLoadsProjectRootAndEditorUrlFromEnv(): void
    {
        $root = __DIR__;
        $editor = 'vscode://file/%file:%line';
        putenv('PHP_ERROR_INSIGHT_ROOT=' . $root);
        putenv('PHP_ERROR_INSIGHT_EDITOR=' . $editor);

        $cfg = Config::fromEnvAndArray();

        $this->assertSame($root, $cfg->projectRoot);
        $this->assertSame($editor, $cfg->editorUrl);
    }

    public function testArrayOptionsOverrideEnv(): void
    {
        putenv('PHP_ERROR_INSIGHT_ROOT=/env/root');
        putenv('PHP_ERROR_INSIGHT_EDITOR=phpstorm://open?file=%file&line=%line');

        $cfg = Config::fromEnvAndArray([
            'projectRoot' => '/custom/root',
            'editorUrl' => 'vscode://file/%file:%line',
        ]);

        $this->assertSame('/custom/root', $cfg->projectRoot);
        $this->assertSame('vscode://file/%file:%line', $cfg->editorUrl);
    }
}
