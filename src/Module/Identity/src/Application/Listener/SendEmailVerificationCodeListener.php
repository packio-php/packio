<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Listener;

use Packio\Identity\Application\Event\EmailVerificationRequested;
use Packio\Identity\Application\Port\AuthMailQueuePort;

final readonly class SendEmailVerificationCodeListener
{
    public function __construct(private AuthMailQueuePort $authMailQueuePort) {}

    public function __invoke(EmailVerificationRequested $emailVerificationRequested): void
    {
        $this->authMailQueuePort->verification($emailVerificationRequested->userUuid->toString(), $emailVerificationRequested->force);
    }
}
