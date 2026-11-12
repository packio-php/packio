<?php

declare(strict_types=1);

namespace Packio\Source\Api\Exception;

enum ProviderFailure: string
{
    case AuthorizationExpired = 'provider_authentication_failed';
    case AccessDenied         = 'provider_access_denied';
    case RateLimited          = 'provider_rate_limited';
    case Unavailable          = 'provider_unavailable';
    case InvalidResponse      = 'provider_invalid_response';
}
