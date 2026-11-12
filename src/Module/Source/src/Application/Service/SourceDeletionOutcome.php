<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

enum SourceDeletionOutcome: string
{
    case Deleted          = 'deleted';
    case AlreadyDeleted   = 'already_deleted';
    case Blocked          = 'blocked';
    case RetryableFailure = 'retryable_failure';
    case StaleLease       = 'stale_lease';
}
