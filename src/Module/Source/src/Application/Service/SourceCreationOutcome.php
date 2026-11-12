<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

enum SourceCreationOutcome: string
{
    case Created          = 'created';
    case Forbidden        = 'forbidden';
    case RetryableFailure = 'retryable_failure';
}
