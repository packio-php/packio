<?php

declare(strict_types=1);

namespace PackioTest\Unit\Package\Infrastructure\Page;

use Packio\Package\Infrastructure\Page\PackageIndexFilterCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackageIndexFilterCriteria::class)]
final class PackageIndexFilterCriteriaTest extends TestCase
{
    public function testFromArrayNormalizesSupportedFilters(): void
    {
        $packageIndexFilterCriteria = PackageIndexFilterCriteria::fromArray([
            'search'             => '  Acme Package  ',
            'visibility'         => ['public', '', 'private', 'public', 'internal'],
            'listed'             => 'listed',
            'source'             => 'managed',
            'source_sync_status' => 'success,failed,unknown',
        ]);

        self::assertSame('Acme Package', $packageIndexFilterCriteria->search);
        self::assertSame(['public', 'private'], $packageIndexFilterCriteria->visibilities);
        self::assertSame('listed', $packageIndexFilterCriteria->listed);
        self::assertSame('managed', $packageIndexFilterCriteria->source);
        self::assertSame(['success', 'failed'], $packageIndexFilterCriteria->sourceSyncStatuses);
        self::assertSame([
            'search'             => 'Acme Package',
            'visibility'         => ['public', 'private'],
            'listed'             => 'listed',
            'source'             => 'managed',
            'source_sync_status' => ['success', 'failed'],
        ], $packageIndexFilterCriteria->toQueryArray());
    }

    public function testFromArrayIgnoresInvalidValues(): void
    {
        $packageIndexFilterCriteria = PackageIndexFilterCriteria::fromArray([
            'search'             => ['invalid'],
            'visibility'         => 'internal',
            'listed'             => 'deleted',
            'source'             => 'external',
            'source_sync_status' => 'unknown',
        ]);

        self::assertNull($packageIndexFilterCriteria->search);
        self::assertSame([], $packageIndexFilterCriteria->visibilities);
        self::assertNull($packageIndexFilterCriteria->listed);
        self::assertNull($packageIndexFilterCriteria->source);
        self::assertSame([], $packageIndexFilterCriteria->sourceSyncStatuses);
    }
}
