<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

enum SourceSyncOutcome: string
{
    case Synchronized          = 'synchronized';
    case Degraded              = 'degraded_success';
    case RetryableFailure      = 'retryable_failure';
    case AuthorizationRequired = 'authorization_required';
    case NotFound              = 'not_found';
}
