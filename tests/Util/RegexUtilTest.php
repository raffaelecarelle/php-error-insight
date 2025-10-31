<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests\Util;

use PhpErrorInsight\Internal\Util\RegexUtil;
use PHPUnit\Framework\TestCase;

final class RegexUtilTest extends TestCase
{
    public function testReplacePerformsReplacement(): void
    {
        $u = new RegexUtil();
        $this->assertSame('hello world', $u->replace('/X/', 'world', 'hello X'));
    }

    public function testReplaceReturnsOriginalOnInvalidPattern(): void
    {
        $u = new RegexUtil();
        // Build an invalid regex pattern dynamically to avoid static analyzers
        $badPattern = '/['; // results in '/[' at runtime (invalid)
        $subject = 'abc';
        $this->assertSame($subject, $u->replace($badPattern, 'x', $subject));
    }
}
