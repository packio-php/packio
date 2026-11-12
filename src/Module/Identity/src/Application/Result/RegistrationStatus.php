<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

enum RegistrationStatus
{
    case Disabled;
    case ChallengeFailed;
    case EmailAlreadyExists;
    case VerificationRequired;
}
