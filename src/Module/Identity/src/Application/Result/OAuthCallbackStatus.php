<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

enum OAuthCallbackStatus
{
    case Authenticated;
    case Banned;
    case SessionExpired;
    case InvalidCode;
    case EmailRequired;
    case VerificationRequired;
    case RegistrationDisabled;
}
