<?php

declare(strict_types=1);

namespace PackioTest\Unit\Shared\Api\Dto;

use Packio\Shared\Api\Dto\PaginatedResultDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginatedResultDtoTest extends TestCase
{
    #[Test]
    public function itKeepsOneAsTheLastPageForAnEmptyResult(): void
    {
        self::assertSame(1, new PaginatedResultDto([], 0, 1, 20)->lastPage());
    }
}
