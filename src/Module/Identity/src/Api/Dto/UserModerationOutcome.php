<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

enum UserModerationOutcome: string
{
    case Success          = 'success';
    case UserNotFound     = 'user_not_found';
    case SelfBanForbidden = 'self_ban_forbidden';
    case LastActiveAdmin  = 'last_active_admin';
    case AlreadyBanned    = 'already_banned';
    case NotBanned        = 'not_banned';
}
