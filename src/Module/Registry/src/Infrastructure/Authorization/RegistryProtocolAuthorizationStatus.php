<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Authorization;

enum RegistryProtocolAuthorizationStatus
{
    case AnonymousAllowed;
    case TokenAllowed;
    case UserBanned;
    case Unauthorized;
    case RateLimited;
    case RateLimitPolicyMissing;
    case PermissionDenied;
    case OrganizationMismatch;
    case TokenOrganizationNotFound;
}
