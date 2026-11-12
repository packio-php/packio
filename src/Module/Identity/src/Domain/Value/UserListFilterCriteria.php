<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Value;

final readonly class UserListFilterCriteria
{
    public function __construct(public ?UserListStatus $status = null) {}
}
