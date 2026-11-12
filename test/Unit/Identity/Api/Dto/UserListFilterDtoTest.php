<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Api\Dto;

use Packio\Identity\Api\Dto\UserListFilterDto;
use Packio\Identity\Api\Dto\UserListStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserListFilterDtoTest extends TestCase
{
    #[Test]
    public function itAcceptsOnlySupportedStatusFilters(): void
    {
        self::assertSame(UserListStatus::Active, UserListFilterDto::fromArray([
            'status' => 'active',
        ])->status);
        self::assertSame(UserListStatus::Banned, UserListFilterDto::fromArray([
            'status' => 'banned',
        ])->status);
        self::assertNull(UserListFilterDto::fromArray([
            'status' => 'all',
        ])->status);
        self::assertNull(UserListFilterDto::fromArray([
            'status' => ['banned'],
        ])->status);
    }
}
