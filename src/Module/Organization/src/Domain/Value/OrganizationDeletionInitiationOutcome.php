<?php

declare(strict_types=1);

namespace Packio\Organization\Domain\Value;

enum OrganizationDeletionInitiationOutcome: string
{
    case Initiated       = 'initiated';
    case AlreadyDeleting = 'already_deleting';
    case NotFound        = 'not_found';
}
