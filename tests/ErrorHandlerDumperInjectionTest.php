<?php

declare(strict_types=1);

use ErrorExplainer\Config;
use ErrorExplainer\Contracts\ExplainerInterface;
use ErrorExplainer\Contracts\RendererInterface;
use ErrorExplainer\Contracts\StateDumperInterface;
use ErrorExplainer\Internal\ErrorHandler;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerDumperInjectionTest extends TestCase
{
    public function testInjectedDumperIsUsed(): void
    {
        $captured = (object)['explanation' => null];

        $fakeExplainer = new class implements ExplainerInterface {
            public function explain(string $kind, string $message, ?string $file, ?int $line, ?array $trace, ?int $severity, Config $config): array
            {
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

        $fakeRenderer = new class($captured) implements RendererInterface {
            private $captured;
            public function __construct($captured) { $this->captured = $captured; }
            public function render(array $explanation, Config $config, string $kind, bool $isShutdown): void
            {
                $this->captured->explanation = $explanation;
            }
        };

        $fakeDumper = new class implements StateDumperInterface {
            public array $lastTrace = [];
            public function collectState(?array $traceFromHandler = null): array
            {
                $this->lastTrace = $traceFromHandler ?? [];
                return ['marker' => 'fake', 'rawTrace' => $this->lastTrace, 'globalsAll' => [], 'definedVars' => [], 'object' => null, 'xdebugText' => ''];
            }
        };

        $config = new Config(['enabled' => true, 'output' => Config::OUTPUT_TEXT]);
        $handler = new ErrorHandler($config, null, null, $fakeExplainer, $fakeRenderer, $fakeDumper);

        $handled = $handler->handleError(E_USER_NOTICE, 'Hi', __FILE__, __LINE__);
        $this->assertTrue($handled);
        $this->assertIsArray($captured->explanation);
        $this->assertSame('fake', $captured->explanation['state']['marker'] ?? null);
        $this->assertIsArray($fakeDumper->lastTrace);
    }
}
