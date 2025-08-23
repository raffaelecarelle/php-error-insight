<?php

declare(strict_types=1);

namespace ErrorExplainer\Tests;

use ErrorExplainer\Config;
use ErrorExplainer\Internal\ErrorHandler;
use ErrorExplainer\Contracts\ExplainerInterface;
use ErrorExplainer\Contracts\RendererInterface;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerFunctionalTest extends TestCase
{
    public function testHandleErrorInvokesExplainerAndRenderer(): void
    {
        $captured = (object)['explanation' => null, 'kind' => null, 'isShutdown' => null];

        $fakeExplainer = new class () implements ExplainerInterface {
            public array $lastArgs;
            public function explain(string $kind, string $message, ?string $file, ?int $line, ?array $trace, ?int $severity, Config $config): array
            {
                $this->lastArgs = func_get_args();
                return [
                    'title' => 'T',
                    'summary' => 'S',
                    'details' => 'D',
                    'suggestions' => ['X'],
                    'severityLabel' => 'Notice',
                    'original' => ['message' => $message, 'file' => $file, 'line' => $line],
                    'trace' => [],
                ];
            }
        };

        $fakeRenderer = new class ($captured) implements RendererInterface {
            private $captured;
            public function __construct($captured)
            {
                $this->captured = $captured;
            }
            public function render(array $explanation, Config $config, string $kind, bool $isShutdown): void
            {
                $this->captured->explanation = $explanation;
                $this->captured->kind = $kind;
                $this->captured->isShutdown = $isShutdown;
                // do not output anything to avoid polluting test output
            }
        };

        $config = new Config([
            'enabled' => true,
            'output' => Config::OUTPUT_TEXT,
            'verbose' => false,
            'backend' => 'none',
        ]);

        $handler = new ErrorHandler($config, null, null, $fakeExplainer, $fakeRenderer);

        $handled = $handler->handleError(E_USER_NOTICE, 'Test message', __FILE__, 42);

        $this->assertTrue($handled);
        $this->assertIsArray($captured->explanation);
        $this->assertSame('error', $captured->kind);
        $this->assertFalse($captured->isShutdown);
        $this->assertSame('Test message', $captured->explanation['original']['message'] ?? null);
    }
}
