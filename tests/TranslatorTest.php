<?php

declare(strict_types=1);

namespace ErrorExplainer\Tests;

use ErrorExplainer\Config;
use ErrorExplainer\Internal\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    public function testTranslateStringUsesLocaleAndFallsBackToEn(): void
    {
        $config = new Config(['language' => 'zz']); // non-existing locale

        // This key exists in en.json
        $out = Translator::t($config, 'labels.summary');
        $this->assertSame('Summary:', $out, 'Should fall back to English when locale not found');
    }

    public function testTranslateStringDefaultsToEnWhenLanguageEmptyOrZero(): void
    {
        $configEmpty = new Config(['language' => '']);
        $configZero = new Config(['language' => '0']);

        $this->assertSame('Summary:', Translator::t($configEmpty, 'labels.summary'));
        $this->assertSame('Summary:', Translator::t($configZero, 'labels.summary'));
    }

    public function testTranslateStringWithPlaceholders(): void
    {
        $config = new Config(['language' => 'en']);
        $out = Translator::t($config, 'ai.prompt', [
            'lang' => 'English',
            'message' => 'Test message',
            'severity' => 'Warning',
            'where' => 'File.php:10',
        ]);

        $this->assertStringContainsString('explains PHP errors in English', $out);
        $this->assertStringContainsString('Message: Test message', $out);
        $this->assertStringContainsString('Severity: Warning', $out);
        $this->assertStringContainsString('Location: File.php:10', $out);
    }

    public function testDotNotationRetrieval(): void
    {
        $config = new Config(['language' => 'en']);
        $this->assertSame('Copy to clipboard', Translator::t($config, 'html.headings.copy'));
    }

    public function testTranslateReturnsKeyWhenMissing(): void
    {
        $config = new Config(['language' => 'en']);
        $missingKey = 'nonexistent.key.path';
        $this->assertSame($missingKey, Translator::t($config, $missingKey));
    }

    public function testTranslateListDefaultsToItAndFallsBackToIt(): void
    {
        // Empty language triggers default for lists -> 'it'
        $configDefault = new Config(['language' => '']);
        $listDefault = Translator::tList($configDefault, 'errors.undefined_variable.suggestions');
        $this->assertNotEmpty($listDefault);
        $this->assertSame("Inizializza la variabile prima dell'uso.", $listDefault[0]);

        // Unknown language -> fallback to 'it' for lists
        $configUnknown = new Config(['language' => 'zz']);
        $listUnknown = Translator::tList($configUnknown, 'errors.undefined_index.suggestions');
        $this->assertNotEmpty($listUnknown);
        $this->assertSame("Verifica l'esistenza della chiave con isset() o array_key_exists() prima di accedere.", $listUnknown[0]);
    }
}
