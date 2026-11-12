<?php

declare(strict_types=1);

namespace Packio\Organization\Api\Dto;

enum OrganizationDeletionInitiationOutcome: string
{
    case Initiated         = 'initiated';
    case AlreadyDeleting   = 'already_deleting';
    case AlreadyInProgress = 'already_in_progress';
    case Rejected          = 'rejected';
    case PublicationFailed = 'publication_failed';
    case NotFound          = 'not_found';
}
