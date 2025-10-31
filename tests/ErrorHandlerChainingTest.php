<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\ExplainerInterface;
use PhpErrorInsight\Contracts\RendererInterface;
use PhpErrorInsight\Internal\ErrorHandler;
use PhpErrorInsight\Internal\Model\Explanation;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use const E_USER_NOTICE;

final class ErrorHandlerChainingTest extends TestCase
{
    private function makeFakeExplainer(): ExplainerInterface
    {
        return new class() implements ExplainerInterface {
            public function explain(string $kind, string $message, ?string $file, ?int $line, ?array $trace, ?int $severity, Config $config): Explanation
            {
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
    }

    private function makeSilentRenderer(?object $sink = null): RendererInterface
    {
        return new class($sink) implements RendererInterface {
            public function __construct(private readonly ?object $sink)
            {
            }

            public function render(Explanation $explanation, Config $config, string $kind, bool $isShutdown): void
            {
                if (null !== $this->sink) {
                    $this->sink->explanation = $explanation;
                    $this->sink->kind = $kind;
                    $this->sink->isShutdown = $isShutdown;
                }
            }
        };
    }

    private function makeInvokeCounter(object $counter): callable
    {
        return new class($counter) {
            public function __construct(private readonly object $counter)
            {
            }

            public function __invoke(): void
            {
                ++$this->counter->count;
            }
        };
    }

    private function makeInvokeCounterEx(object $counter): callable
    {
        return new class($counter) {
            public function __construct(private readonly object $counter)
            {
            }

            public function __invoke(Throwable $e): void
            {
                ++$this->counter->count;
            }
        };
    }

    public function testPreviousErrorHandlerIsCalledWhenPresent(): void
    {
        $called = (object) ['count' => 0];
        $prev = $this->makeInvokeCounter($called);

        $config = new Config(['enabled' => true, 'output' => Config::OUTPUT_TEXT]);
        $handler = new ErrorHandler($config, $prev, null, $this->makeFakeExplainer(), $this->makeSilentRenderer());

        $handled = $handler->handleError(E_USER_NOTICE, 'Chaining', __FILE__, __LINE__);

        $this->assertTrue($handled);
        $this->assertSame(1, $called->count, 'Previous error handler should be called exactly once');
    }

    public function testHandleExceptionCallsPreviousWhenDisabled(): void
    {
        $called = (object) ['count' => 0];
        $prevEx = $this->makeInvokeCounterEx($called);

        $config = new Config(['enabled' => false, 'output' => Config::OUTPUT_TEXT]);
        $handler = new ErrorHandler($config, null, $prevEx, $this->makeFakeExplainer(), $this->makeSilentRenderer());

        $handler->handleException(new RuntimeException('Boom'));
        $this->assertSame(1, $called->count, 'Previous exception handler should be called when disabled');
    }

    public function testHandleExceptionRethrowsWhenDisabledAndNoPrevious(): void
    {
        $this->expectException(RuntimeException::class);
        $config = new Config(['enabled' => false, 'output' => Config::OUTPUT_TEXT]);
        $handler = new ErrorHandler($config, null, null, $this->makeFakeExplainer(), $this->makeSilentRenderer());
        $handler->handleException(new RuntimeException('Boom'));
    }
}
