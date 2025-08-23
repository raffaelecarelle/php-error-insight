<?php

declare(strict_types=1);

namespace ErrorExplainer\Tests;

use ErrorExplainer\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testFromEnvAndArrayMergesProperly(): void
    {
        $prevBackend = getenv('PHP_ERROR_INSIGHT_BACKEND');
        $prevLang = getenv('PHP_ERROR_INSIGHT_LANG');
        putenv('PHP_ERROR_INSIGHT_BACKEND=api');
        putenv('PHP_ERROR_INSIGHT_LANG=en');

        try {
            $cfg = Config::fromEnvAndArray([
                'backend' => 'none', // override env
                'language' => 'it',  // override env
                'verbose' => true,
            ]);

            $this->assertSame('none', $cfg->backend);
            $this->assertSame('it', $cfg->language);
            $this->assertTrue($cfg->verbose);
        } finally {
            // restore env
            if ($prevBackend === false) {
                putenv('PHP_ERROR_INSIGHT_BACKEND');
            } else {
                putenv('PHP_ERROR_INSIGHT_BACKEND=' . $prevBackend);
            }
            if ($prevLang === false) {
                putenv('PHP_ERROR_INSIGHT_LANG');
            } else {
                putenv('PHP_ERROR_INSIGHT_LANG=' . $prevLang);
            }
        }
    }
}
