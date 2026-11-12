<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

enum SignInStatus
{
    case InvalidCredentials;
    case Authenticated;
    case Banned;
    case VerificationRequired;
}
