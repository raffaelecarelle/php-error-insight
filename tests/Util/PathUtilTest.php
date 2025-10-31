<?php

declare(strict_types=1);

namespace PhpErrorInsight\Tests\Util;

use PhpErrorInsight\Internal\Util\PathUtil;
use PHPUnit\Framework\TestCase;

use const DIRECTORY_SEPARATOR;

final class PathUtilTest extends TestCase
{
    public function testNormalizeSlashesCompressesAndUnifies(): void
    {
        $u = new PathUtil();
        $this->assertSame('/a/b/c', $u->normalizeSlashes('\\a//b///c'));
        $this->assertSame('/a/b', $u->normalizeSlashes('/a////b'));
    }

    public function testRtrimSepRemovesTrailingDirectorySeparator(): void
    {
        $u = new PathUtil();
        $sep = DIRECTORY_SEPARATOR;
        $this->assertSame('/path', $u->rtrimSep('/path' . $sep));
        $this->assertSame('/path/inner', $u->rtrimSep('/path/inner' . $sep . $sep));
        $this->assertSame('/path', $u->rtrimSep('/path')); // unchanged when no trailing sep
    }

    public function testRealReturnsInputWhenPathDoesNotExist(): void
    {
        $u = new PathUtil();
        $nonExisting = __DIR__ . '/definitely-not-existing-' . uniqid('', true);
        $this->assertSame($nonExisting, $u->real($nonExisting));
    }

    public function testToEditorHrefMapsProjectRootToHostAndInjectsFileAndLine(): void
    {
        $u = new PathUtil();
        $tpl = 'vscode://file/%file:%line';
        $projectRoot = '/work/project';
        $hostRoot = '/host/prj';
        $file = '/work/project/src/Foo.php';
        $line = 42;

        $href = $u->toEditorHref($tpl, $file, $line, $projectRoot, $hostRoot);

        $this->assertSame('vscode://file//host/prj/src/Foo.php:42', $href);
    }

    public function testToEditorHrefDoesNotRewriteWhenDifferentRoots(): void
    {
        $u = new PathUtil();
        $tpl = 'phpstorm://open?file=%file&line=%line';
        $projectRoot = '/work/project';
        $hostRoot = '/host/prj';
        $file = '/another/place/Foo.php';
        $line = 7;

        $href = $u->toEditorHref($tpl, $file, $line, $projectRoot, $hostRoot);

        $this->assertSame('phpstorm://open?file=/another/place/Foo.php&line=7', $href);
    }
}
