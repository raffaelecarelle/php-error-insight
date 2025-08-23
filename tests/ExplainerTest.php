<?php

declare(strict_types=1);

use ErrorExplainer\Config;
use ErrorExplainer\Internal\Explainer;
use ErrorExplainer\Contracts\AIClientInterface;
use PHPUnit\Framework\TestCase;

final class ExplainerTest extends TestCase
{
    public function testExplainUsesInjectedAIClientAndExtractsBullets(): void
    {
        $fakeAi = new class implements AIClientInterface {
            public function generateExplanation(string $prompt, Config $config): ?string
            {
                return "* Do A\n* Do B";
            }
        };

        $config = new Config([
            'backend' => 'api',
            'language' => 'en',
            'verbose' => true,
        ]);
        $explainer = new Explainer($fakeAi);

        $out = $explainer->explain('error', 'Undefined variable: foo', __FILE__, 123, [], E_USER_NOTICE, $config);

        $this->assertIsArray($out);
        $this->assertSame('PHP Error Explanation (with AI)', $out['title']);
        $this->assertNotEmpty($out['suggestions']);
        $this->assertContains('Do A', $out['suggestions']);
        $this->assertContains('Do B', $out['suggestions']);
        $this->assertStringContainsString('[AI]', $out['details']);
    }

    public function testExplainWithoutAIClientFallsBackToTranslations(): void
    {
        $config = new Config([
            'backend' => 'api',
            'language' => 'en',
            'verbose' => false,
        ]);
        $explainer = new Explainer();

        $out = $explainer->explain('error', 'Undefined variable: foo', __FILE__, 123, [], E_USER_NOTICE, $config);

        $this->assertIsArray($out);
        $this->assertSame('PHP Error Explanation', $out['title']);
        $this->assertEmpty($out['summary']);
        $this->assertEmpty($out['suggestions']);
        $this->assertIsString($out['details']);
        $this->assertStringNotContainsString('[AI]', $out['details']);
    }
}
