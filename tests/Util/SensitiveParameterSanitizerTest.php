<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests\Util;

use PhpErrorInsight\Internal\Util\SensitiveParameterSanitizer;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SensitiveParameterSanitizerTest extends TestCase
{
    public function testSanitizeArgsWithSensitiveParameter(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        // Test with a real function that has sensitive parameter
        $result = $sanitizer->sanitizeArgs(
            self::class,
            '->',
            'methodWithSensitiveParam',
            ['user@example.com', 'secret_password']
        );

        $this->assertSame('user@example.com', $result[0]);
        $this->assertSame('***MASKED***', $result[1]);
    }

    public function testSanitizeArgsWithoutSensitiveParameter(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        $result = $sanitizer->sanitizeArgs(
            self::class,
            '->',
            'methodWithoutSensitiveParam',
            ['public_data', 'more_data']
        );

        $this->assertSame('public_data', $result[0]);
        $this->assertSame('more_data', $result[1]);
    }

    public function testSanitizeArgsWithEmptyArgs(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        $result = $sanitizer->sanitizeArgs('', '', '', []);

        $this->assertSame([], $result);
    }

    public function testSanitizeArgsWithNonExistentFunction(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        $args = ['arg1', 'arg2'];
        $result = $sanitizer->sanitizeArgs('NonExistent', '->', 'method', $args);

        // Should return original args when reflection fails
        $this->assertSame($args, $result);
    }

    public function testSanitizeTextMasksAuthorizationHeader(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        $text = 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature';
        $result = $sanitizer->sanitizeText($text);

        $this->assertStringContainsString('***MASKED***', $result);
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $result);
    }

    public function testSanitizeTextMasksJwtTokens(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        $text = 'Token: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U';
        $result = $sanitizer->sanitizeText($text);

        $this->assertStringContainsString('***MASKED***', $result);
        $this->assertStringNotContainsString('eyJzdWIiOiIxMjM0NTY3ODkwIn0', $result);
    }

    public function testSanitizeTextMasksEmailAddresses(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        $text = 'User email: user@example.com and admin@test.org';
        $result = $sanitizer->sanitizeText($text);

        $this->assertStringContainsString('***MASKED***', $result);
        $this->assertStringNotContainsString('user@example.com', $result);
        $this->assertStringNotContainsString('admin@test.org', $result);
    }

    public function testSanitizeTextMasksApiKeys(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        $text = 'api_key=sk-1234567890abcdef and password: mySecretPassword123';
        $result = $sanitizer->sanitizeText($text);

        $this->assertStringContainsString('***MASKED***', $result);
        $this->assertStringNotContainsString('sk-1234567890abcdef', $result);
        $this->assertStringNotContainsString('mySecretPassword123', $result);
    }

    public function testSanitizeTextPreservesNonSensitiveData(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        $text = 'Error in file /path/to/file.php on line 42';
        $result = $sanitizer->sanitizeText($text);

        $this->assertSame($text, $result);
    }

    public function testSanitizeRecursiveWithArray(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        $data = [
            'key1' => 'value1',
            'key2' => ['nested' => 'value2'],
        ];

        $result = $sanitizer->sanitizeRecursive($data);

        $this->assertSame('value1', $result['key1']);
        $this->assertSame('value2', $result['key2']['nested']);
    }

    public function testSanitizeRecursiveWithObject(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        $object = new stdClass();
        $object->prop = 'value';

        $result = $sanitizer->sanitizeRecursive($object);

        $this->assertIsString($result);
        $this->assertStringContainsString('stdClass', $result);
    }

    public function testSanitizeRecursiveWithScalar(): void
    {
        $sanitizer = new SensitiveParameterSanitizer();

        $this->assertSame('test', $sanitizer->sanitizeRecursive('test'));
        $this->assertSame(123, $sanitizer->sanitizeRecursive(123));
        $this->assertTrue($sanitizer->sanitizeRecursive(true));
    }

    public function testCustomMask(): void
    {
        $sanitizer = new SensitiveParameterSanitizer('[HIDDEN]');

        $text = 'api_key=sk-1234567890abcdef';
        $result = $sanitizer->sanitizeText($text);

        $this->assertStringContainsString('[HIDDEN]', $result);
        $this->assertStringNotContainsString('***MASKED***', $result);
    }
}
