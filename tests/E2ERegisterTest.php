<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests;

use PhpErrorInsight\Config;
use PhpErrorInsight\ErrorExplainer;
use PHPUnit\Framework\TestCase;
use Throwable;

use const E_USER_WARNING;

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
        try {
            @trigger_error('Boom E2E', E_USER_WARNING);
            $out = (string) ob_get_clean();
        } catch (Throwable $throwable) {
            ob_end_clean();
            throw $throwable;
        }

        $this->assertNotSame('', $out);
        $this->assertStringContainsString('"original"', $out);

        $decoded = json_decode($out, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Boom E2E', $decoded['original']['message'] ?? null);
        $this->assertArrayHasKey('trace', $decoded);
    }
}
