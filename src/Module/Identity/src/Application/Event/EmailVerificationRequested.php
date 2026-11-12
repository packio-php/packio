<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Event;

use Cake\Chronos\Chronos;
use Packio\Identity\Domain\EmailVerificationReason;
use Ramsey\Uuid\UuidInterface;

final readonly class EmailVerificationRequested
{
    public function __construct(
        public UuidInterface $userUuid,
        public bool $force,
        public EmailVerificationReason $reason,
        public Chronos $requestedAt = new Chronos(),
    ) {}
}
