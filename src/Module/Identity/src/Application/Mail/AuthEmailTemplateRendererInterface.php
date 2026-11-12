<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Mail;

interface AuthEmailTemplateRendererInterface
{
    public function renderEmailVerificationCode(string $code, int $ttlMinutes): RenderedEmail;

    public function renderPasswordResetLink(string $resetUrl, int $ttlMinutes): RenderedEmail;
}
