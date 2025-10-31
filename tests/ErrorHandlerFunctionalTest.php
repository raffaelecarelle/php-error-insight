<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\ExplainerInterface;
use PhpErrorInsight\Contracts\RendererInterface;
use PhpErrorInsight\Internal\ErrorHandler;
use PhpErrorInsight\Internal\Model\Explanation;
use PHPUnit\Framework\TestCase;

use function func_get_args;

use const E_USER_NOTICE;

final class ErrorHandlerFunctionalTest extends TestCase
{
    public function testHandleErrorInvokesExplainerAndRenderer(): void
    {
        $captured = (object) ['explanation' => null, 'kind' => null, 'isShutdown' => null];

        $fakeExplainer = new class() implements ExplainerInterface {
            /** @var array<int, mixed> */
            public array $lastArgs;

            public function explain(string $kind, string $message, ?string $file, ?int $line, ?array $trace, ?int $severity, Config $config, ?string $exceptionClass = null): Explanation
            {
                $this->lastArgs = func_get_args();

                return Explanation::fromArray([
                    'title' => 'T',
                    'summary' => 'S',
                    'details' => 'D',
                    'suggestions' => ['X'],
                    'severityLabel' => 'Notice',
                    'original' => ['message' => $message, 'file' => $file, 'line' => $line],
                    'trace' => [],
                ]);
            }
        };

        $fakeRenderer = new class($captured) implements RendererInterface {
            public function __construct(private readonly object $captured)
            {
            }

            public function render(Explanation $explanation, Config $config, string $kind, bool $isShutdown): void
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
        $this->assertInstanceOf(Explanation::class, $captured->explanation);
        $this->assertSame('error', $captured->kind);
        $this->assertFalse($captured->isShutdown);
        $this->assertSame('Test message', $captured->explanation->original['message'] ?? null);
    }
}
