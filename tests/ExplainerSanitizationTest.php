<?php

declare(strict_types=1);

namespace ErrorExplainer\Tests;

use ErrorExplainer\Config;
use ErrorExplainer\Contracts\AIClientInterface;
use ErrorExplainer\Internal\Explainer;
use PHPUnit\Framework\TestCase;

final class ExplainerSanitizationTest extends TestCase
{
    protected function setUp(): void
    {
        // Force sanitization on and default mask for predictable assertions
        $this->prevSan = getenv('PHP_ERROR_INSIGHT_SANITIZE');
        $this->prevRules = getenv('PHP_ERROR_INSIGHT_SANITIZE_RULES');
        $this->prevMask = getenv('PHP_ERROR_INSIGHT_SANITIZE_MASK');
        putenv('PHP_ERROR_INSIGHT_SANITIZE=1');
        putenv('PHP_ERROR_INSIGHT_SANITIZE_RULES=secrets,pii,network');
        putenv('PHP_ERROR_INSIGHT_SANITIZE_MASK=***REDACTED***');
    }

    protected function tearDown(): void
    {
        // restore env
        if ($this->prevSan === false) {
            putenv('PHP_ERROR_INSIGHT_SANITIZE');
        } else {
            putenv('PHP_ERROR_INSIGHT_SANITIZE='.(string)$this->prevSan);
        }
        if ($this->prevRules === false) {
            putenv('PHP_ERROR_INSIGHT_SANITIZE_RULES');
        } else {
            putenv('PHP_ERROR_INSIGHT_SANITIZE_RULES='.(string)$this->prevRules);
        }
        if ($this->prevMask === false) {
            putenv('PHP_ERROR_INSIGHT_SANITIZE_MASK');
        } else {
            putenv('PHP_ERROR_INSIGHT_SANITIZE_MASK='.(string)$this->prevMask);
        }
    }

    public function testPromptIsSanitizedBeforeDelegation(): void
    {
        $captured = (object)['prompt' => null];
        $fakeAi = new class ($captured) implements AIClientInterface {
            private $c;
            public function __construct($c)
            {
                $this->c = $c;
            }
            public function generateExplanation(string $prompt, Config $config): ?string
            {
                $this->c->prompt = $prompt;
                return "ok";
            }
        };
        $explainer = new Explainer($fakeAi);
        $config = new Config(['backend' => 'api', 'language' => 'en']);

        // Message includes an email and a cookie header to be redacted
        $msg = "Email john@example.com\nCookie: abc=def\n";
        $explainer->explain('error', $msg, __FILE__, 10, [], null, $config);

        $this->assertIsString($captured->prompt);
        $this->assertStringNotContainsString('john@example.com', $captured->prompt);
        $this->assertStringContainsString('***@***', $captured->prompt);
        $this->assertStringContainsString('Cookie: ***REDACTED***', $captured->prompt);
    }

    public function testEnvCanDisableSanitization(): void
    {
        putenv('PHP_ERROR_INSIGHT_SANITIZE=0');
        $captured = (object)['prompt' => null];
        $fakeAi = new class ($captured) implements AIClientInterface {
            private $c;
            public function __construct($c)
            {
                $this->c = $c;
            }
            public function generateExplanation(string $prompt, Config $config): ?string
            {
                $this->c->prompt = $prompt;
                return "ok";
            }
        };
        $explainer = new Explainer($fakeAi);
        $config = new Config(['backend' => 'api', 'language' => 'en']);

        $msg = "Email john@example.com\n";
        $explainer->explain('error', $msg, __FILE__, 10, [], null, $config);

        $this->assertIsString($captured->prompt);
        $this->assertStringContainsString('john@example.com', $captured->prompt);
        $this->assertStringNotContainsString('***@***', $captured->prompt);
    }
}
