<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

enum SourceDeletionInitiationOutcome: string
{
    case Initiated       = 'initiated';
    case AlreadyDeleting = 'already_deleting';
    case NotFound        = 'not_found';
}
