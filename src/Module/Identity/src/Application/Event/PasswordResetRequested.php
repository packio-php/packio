<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Event;

use Cake\Chronos\Chronos;
use Ramsey\Uuid\UuidInterface;

final readonly class PasswordResetRequested
{
    public function __construct(
        public UuidInterface $userUuid,
        public string $userEmail,
        public string $selector,
        public string $secret,
        public Chronos $requestedAt = new Chronos(),
    ) {}
}
