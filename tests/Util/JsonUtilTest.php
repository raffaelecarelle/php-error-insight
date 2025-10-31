<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests\Util;

use PhpErrorInsight\Internal\Util\JsonUtil;
use PHPUnit\Framework\TestCase;

final class JsonUtilTest extends TestCase
{
    public function testEncodeReturnsEmptyStringOnInvalidUtf8(): void
    {
        $u = new JsonUtil();
        // Invalid UTF-8 byte sequence inside a string value
        $bad = "\xB1\x31"; // not valid UTF-8
        $json = $u->encode(['s' => $bad]);
        $this->assertSame('', $json, 'Expected empty string when json_encode fails');
    }

    public function testDecodeObjectReturnsArrayOnValidJson(): void
    {
        $u = new JsonUtil();
        $data = $u->decodeObject('{"a":1, "b":"x"}');
        $this->assertIsArray($data);
        $this->assertSame(['a' => 1, 'b' => 'x'], $data);
    }

    public function testDecodeObjectReturnsNullOnMalformedOrScalar(): void
    {
        $u = new JsonUtil();
        $this->assertNull($u->decodeObject('{malformed'));
        // Scalar JSON (number) decodes to int, not array
        $this->assertNull($u->decodeObject('123'));
    }
}
