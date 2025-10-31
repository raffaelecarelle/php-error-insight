<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests;

use PhpErrorInsight\Config;
use PhpErrorInsight\Contracts\AIAdapterFactoryInterface;
use PhpErrorInsight\Contracts\AIClientInterface;
use PhpErrorInsight\Internal\Explainer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use const E_USER_NOTICE;

final class ExplainerTest extends TestCase
{
    public function testExplainUsesInjectedAIClientAndExtractsSuggestions(): void
    {
        $fakeAi = new class() implements AIClientInterface {
            public function generateExplanation(string $prompt, Config $config): ?string
            {
                return '{"details": "[AI]", "suggestions": ["Do A", "Do B", "Do B"]}';
            }
        };

        /** @var AIAdapterFactoryInterface&MockObject $factory */
        $factory = $this->createMock(AIAdapterFactoryInterface::class);
        $factory->method('make')
            ->with('api')
            ->willReturn($fakeAi);

        $config = new Config([
            'backend' => 'api',
            'language' => 'en',
            'verbose' => true,
        ]);

        $explainer = new Explainer($factory);

        $out = $explainer->explain('error', 'Undefined variable: foo', __FILE__, 123, [], E_USER_NOTICE, $config);

        $this->assertIsArray($out);
        $this->assertSame('Undefined variable: foo', $out['title']);
        $this->assertNotEmpty($out['suggestions']);
        $this->assertContains('Do A', $out['suggestions']);
        $this->assertContains('Do B', $out['suggestions']);
        $this->assertStringContainsString('[AI]', $out['details']);
    }
}
