<?php

declare(strict_types=1);

namespace Packio\Identity\Domain;

/**
 * Domain enum for email verification reasons.
 * Used by application events and domain logic.
 */
enum EmailVerificationReason: string
{
    case Registration = 'registration';
    case Login        = 'login';
    case ManualResend = 'manual_resend';
}
