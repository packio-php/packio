<?php

declare(strict_types=1);

namespace Packio\Package\Application\Deletion;

enum PackageDeletionOutcome: string
{
    case Deleted          = 'deleted';
    case AlreadyDeleted   = 'already_deleted';
    case RetryableFailure = 'retryable_failure';
    case Blocked          = 'blocked';
}
