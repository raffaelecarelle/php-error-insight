<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests\Util;

use PhpErrorInsight\Internal\Util\ArrayUtil;
use PHPUnit\Framework\TestCase;

final class ArrayUtilTest extends TestCase
{
    public function testUniqueReindexesArray(): void
    {
        $u = new ArrayUtil();
        $input = ['a', 'b', 'a'];
        $unique = $u->unique($input);
        $this->assertSame(['a', 'b'], $unique);
        $this->assertSame([0, 1], array_keys($unique));
    }

    public function testFilterReindexesArray(): void
    {
        $u = new ArrayUtil();
        $input = [1, 2, 3, 4];
        $even = $u->filter($input, fn (int $v): bool => 0 === $v % 2);
        $this->assertSame([2, 4], $even);
        $this->assertSame([0, 1], array_keys($even));
    }

    public function testFirstReturnsDefaultWhenEmpty(): void
    {
        $u = new ArrayUtil();
        $this->assertSame('x', $u->first([], 'x'));
        $this->assertSame(1, $u->first([1, 2, 3], 9));
    }

    public function testInArrayStrictUsesStrictComparison(): void
    {
        $u = new ArrayUtil();
        $this->assertTrue($u->inArrayStrict('1', ['1', '2']));
        $this->assertFalse($u->inArrayStrict('1', [1, 2]));
    }
}
