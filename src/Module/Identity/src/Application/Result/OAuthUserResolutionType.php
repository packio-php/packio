<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

enum OAuthUserResolutionType
{
    case Linked;
    case ExistingEmail;
    case Created;
    case EmailRequired;
}
