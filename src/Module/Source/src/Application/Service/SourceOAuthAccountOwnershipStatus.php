<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

enum SourceOAuthAccountOwnershipStatus
{
    case Allowed;
    case NotFound;
    case Unavailable;
    case TokenUnavailable;
}
