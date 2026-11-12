<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Page;

use Packio\Source\Infrastructure\Page\SourceIndexFilterCriteria;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceIndexFilterCriteria::class)]
final class SourceIndexFilterCriteriaTest extends TestCase
{
    public function testFromArrayNormalizesSupportedFilters(): void
    {
        $sourceIndexFilterCriteria = SourceIndexFilterCriteria::fromArray([
            'search'      => '  Acme Repo  ',
            'provider'    => ['github', '', 'gitlab', 'github'],
            'visibility'  => 'public,private,internal',
            'status'      => ['active', 'archived', 'deleting'],
            'sync_status' => 'success,failed,unknown',
        ]);

        self::assertSame('Acme Repo', $sourceIndexFilterCriteria->search);
        self::assertSame(['github', 'gitlab'], $sourceIndexFilterCriteria->providers);
        self::assertSame(['public', 'private'], $sourceIndexFilterCriteria->visibilities);
        self::assertSame(['active', 'archived'], $sourceIndexFilterCriteria->statuses);
        self::assertSame(['success', 'failed'], $sourceIndexFilterCriteria->syncStatuses);
        self::assertSame([
            'search'      => 'Acme Repo',
            'provider'    => ['github', 'gitlab'],
            'visibility'  => ['public', 'private'],
            'status'      => ['active', 'archived'],
            'sync_status' => ['success', 'failed'],
        ], $sourceIndexFilterCriteria->toQueryArray());
    }

    public function testFromArrayIgnoresInvalidScalarValues(): void
    {
        $sourceIndexFilterCriteria = SourceIndexFilterCriteria::fromArray([
            'search'      => ['invalid'],
            'provider'    => 123,
            'visibility'  => 'internal',
            'status'      => 'deleted',
            'sync_status' => 'unknown',
        ]);

        self::assertNull($sourceIndexFilterCriteria->search);
        self::assertSame(['123'], $sourceIndexFilterCriteria->providers);
        self::assertSame([], $sourceIndexFilterCriteria->visibilities);
        self::assertSame([], $sourceIndexFilterCriteria->statuses);
        self::assertSame([], $sourceIndexFilterCriteria->syncStatuses);
    }
}
