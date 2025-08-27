<?php

declare(strict_types=1);

namespace ErrorExplainer\Tests;

use ErrorExplainer\Internal\DefaultSensitiveDataSanitizer;
use ErrorExplainer\Internal\SanitizerConfig;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase
{
    private function sanitize(string $text, ?callable $cfgCb = null): string
    {
        $san = new DefaultSensitiveDataSanitizer();
        $cfg = new SanitizerConfig();
        if ($cfgCb) {
            $cfgCb($cfg);
        }
        return $san->sanitize($text, $cfg);
    }

    public function testAuthorizationHeaderIsRedacted(): void
    {
        $in = "Authorization: Bearer abc.DEF.ghi123\nNext: line";
        $out = $this->sanitize($in);
        $this->assertStringContainsString('Authorization: Bearer ***REDACTED***', $out);
    }

    public function testJwtLikeTokenIsRedacted(): void
    {
        $in = 'token eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0In0.VeryLongSignature123';
        $out = $this->sanitize($in);
        $this->assertStringNotContainsString('eyJhbGci', $out);
        $this->assertStringContainsString('***REDACTED***', $out);
    }

    public function testEmailAndPhoneAreMasked(): void
    {
        $in = 'Contact me at john.doe@example.com or +39 340 123 4567';
        $out = $this->sanitize($in);
        $this->assertStringNotContainsString('john.doe@example.com', $out);
        $this->assertStringNotContainsString('+39 340 123 4567', $out);
        $this->assertStringContainsString('***@***', $out);
    }

    public function testItalianCFAndIBANAreRedacted(): void
    {
        $in = 'CF RSSMRA85T10A562S and IBAN IT60X0542811101000000123456';
        $out = $this->sanitize($in);
        $this->assertStringNotContainsString('RSSMRA85T10A562S', $out);
        $this->assertStringNotContainsString('IT60X0542811101000000123456', $out);
        $this->assertStringContainsString('***REDACTED***', $out);
    }

    public function testPaymentLikeNumberIsRedacted(): void
    {
        $in = 'card 4111 1111 1111 1111 exp 12/29';
        $out = $this->sanitize($in);
        $this->assertStringNotContainsString('4111 1111 1111 1111', $out);
        $this->assertStringContainsString('***REDACTED***', $out);
    }

    public function testPrivateIPsAndCookieAreRedacted(): void
    {
        $in = "IP 192.168.1.100\nCookie: sessionid=abcdef\n";
        $out = $this->sanitize($in);
        $this->assertStringNotContainsString('192.168.1.100', $out);
        $this->assertStringContainsString('Cookie: ***REDACTED***', $out);
    }

    public function testCustomMaskAndRulesFromConfig(): void
    {
        $in = 'Contact: a@b.it 4111111111111111';
        $out = $this->sanitize($in, function (SanitizerConfig $cfg) {
            $cfg->masks['default'] = '<MASK>';
            $cfg->enabledRules = ['pii']; // disable payment
        });
        $this->assertStringContainsString('***@***', $out); // email mask remains specific
        $this->assertStringContainsString('4111111111111111', $out); // payment not applied
        $this->assertStringNotContainsString('<MASK>', $out); // default mask not required for email
    }

    public function testCustomRegexHasPriority(): void
    {
        $in = 'SecretKey=XYZ-123 and mail x@y.it';
        $out = $this->sanitize($in, function (SanitizerConfig $cfg) {
            $cfg->customRegex['/SecretKey=\w+-\d+/'] = '<S>'; // custom first
        });
        $this->assertStringContainsString('<S>', $out);
        $this->assertStringContainsString('***@***', $out);
    }
}
