<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Repository;

use Packio\Identity\Domain\Entity\EmailVerificationCode;

interface EmailVerificationCodeRepositoryInterface
{
    public function save(EmailVerificationCode $emailVerificationCode): void;

    public function delete(EmailVerificationCode $emailVerificationCode): void;

    public function findActiveByUserUuid(string $userUuid): ?EmailVerificationCode;

    public function deleteExpired(): int;
}
