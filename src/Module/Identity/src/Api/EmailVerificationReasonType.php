<?php

declare(strict_types=1);

namespace Packio\Identity\Api;

enum EmailVerificationReasonType: string
{
    case Registration = 'registration';
    case Login        = 'login';
    case ManualResend = 'manual_resend';
}
