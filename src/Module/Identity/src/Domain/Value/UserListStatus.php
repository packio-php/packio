<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Value;

enum UserListStatus: string
{
    case Active = 'active';
    case Banned = 'banned';
}
