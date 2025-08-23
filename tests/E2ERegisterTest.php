<?php

declare(strict_types=1);

namespace ErrorExplainer\Tests;

use ErrorExplainer\Config;
use ErrorExplainer\ErrorExplainer;
use PHPUnit\Framework\TestCase;

final class E2ERegisterTest extends TestCase
{
    protected function tearDown(): void
    {
        // Ensure global handlers are cleaned up after each test
        ErrorExplainer::unregister();
        parent::tearDown();
    }

    public function testRegisterAndTriggerUserWarningOutputsJsonWithOriginalMessage(): void
    {
        // Register with JSON output to assert on structured data
        ErrorExplainer::register([
            'output' => Config::OUTPUT_JSON,
            'language' => 'en',
            'backend' => 'none',
            'verbose' => false,
        ]);

        ob_start();
        @trigger_error('Boom E2E', E_USER_WARNING);
        $out = (string)ob_get_clean();

        $this->assertNotSame('', $out);
        $this->assertStringContainsString('"original"', $out);

        $decoded = json_decode($out, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Boom E2E', $decoded['original']['message'] ?? null);
        $this->assertArrayHasKey('trace', $decoded);
        $this->assertArrayHasKey('state', $decoded);
    }
}
