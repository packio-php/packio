<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

enum SourceWebhookRegistrationOutcome: string
{
    case Skipped           = 'skipped';
    case Registered        = 'registered';
    case AlreadyRegistered = 'already_registered';
    case Warning           = 'warning';
}
