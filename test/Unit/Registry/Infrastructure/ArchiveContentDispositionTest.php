<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure;

use Packio\Registry\Infrastructure\ArchiveContentDisposition;
use PHPUnit\Framework\TestCase;

final class ArchiveContentDispositionTest extends TestCase
{
    public function testOrdinaryFilenameRemainsCompatible(): void
    {
        self::assertSame('attachment; filename="package-abc.zip"', ArchiveContentDisposition::attachment('package-abc.zip'));
    }

    public function testQuotesAndUnicodeUseEncodedExtendedParameter(): void
    {
        self::assertSame('attachment; filename="package-___.zip"; filename*=UTF-8\'\'package-%22%C3%A9.zip', ArchiveContentDisposition::attachment('package-"é.zip'));
    }

    public function testRemovesControlCharactersAndPathSeparators(): void
    {
        $header = ArchiveContentDisposition::attachment("../a\\b\r\n.zip");
        self::assertSame('attachment; filename=".._a_b__.zip"', $header);
        self::assertSame('attachment; filename="archive.zip"; filename*=UTF-8\'\'..', ArchiveContentDisposition::attachment('..'));
    }
}
