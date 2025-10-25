<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests;

use PhpErrorInsight\Internal\StateDumper;
use PHPUnit\Framework\TestCase;

final class StateDumperTest extends TestCase
{
    public function testCollectStateReturnsExpectedKeys(): void
    {
        $d = new StateDumper();
        $out = $d->collectState();
        $this->assertIsArray($out);
        foreach (['globalsAll', 'definedVars', 'rawTrace', 'object', 'xdebugText'] as $k) {
            $this->assertArrayHasKey($k, $out);
        }
    }
}
