<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Port;

interface AuthMailQueuePort
{
    public function verification(string $userId, bool $force): void;

    public function passwordReset(string $email, ?string $ip, ?string $userAgent): void;
}
