<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

enum UserListStatus: string
{
    case Active = 'active';
    case Banned = 'banned';
}
