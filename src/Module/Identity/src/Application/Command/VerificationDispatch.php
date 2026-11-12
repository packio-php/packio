<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command;

use Common\Logging\SafeExceptionContext;
use Packio\Identity\Application\Event\EmailVerificationRequested;
use Packio\Identity\Domain\EmailVerificationReason;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

final readonly class VerificationDispatch
{
    public function __construct(private EventDispatcherInterface $eventDispatcher, private LoggerInterface $logger) {}

    public function request(string $userId, EmailVerificationReason $emailVerificationReason): void
    {
        try {
            $this->eventDispatcher->dispatch(new EmailVerificationRequested(Uuid::fromString($userId), false, $emailVerificationReason));
        } catch (Throwable $e) {
            // The verification screen allows retrying delivery without losing the pending user.
            $this->logger->warning('Verification email dispatch failed.', SafeExceptionContext::exception($e));
        }
    }
}
