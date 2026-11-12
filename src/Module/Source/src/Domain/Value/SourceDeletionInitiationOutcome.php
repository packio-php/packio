<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Value;

enum SourceDeletionInitiationOutcome: string
{
    case Initiated       = 'initiated';
    case AlreadyDeleting = 'already_deleting';
    case NotFound        = 'not_found';
}
