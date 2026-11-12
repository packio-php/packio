<?php

declare(strict_types=1);

namespace PackioTest\Unit\Shared\Domain\Pagination;

use Packio\Shared\Domain\Pagination\PaginationCriteria;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginationCriteriaTest extends TestCase
{
    #[Test]
    public function itNormalizesAnOverflowingPageBeforeCalculatingOffset(): void
    {
        $paginationCriteria = PaginationCriteria::fromArray([
            'page'     => (string) PHP_INT_MAX,
            'per_page' => '100',
        ]);

        self::assertSame(1, $paginationCriteria->page);
        self::assertSame(0, $paginationCriteria->offset());
    }
}
