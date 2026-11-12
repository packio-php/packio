<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Repository;

use Packio\Identity\Domain\Entity\PasswordResetToken;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface PasswordResetTokenRepositoryInterface
{
    public function save(PasswordResetToken $passwordResetToken): void;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;

    public function findBySelector(string $selector): ?PasswordResetToken;

    public function findActiveBySelector(string $selector): ?PasswordResetToken;

    public function deleteActiveByUserUuid(string $userUuid, ?string $excludeTokenUuid = null): int;

    public function deleteExpired(): int;
}
