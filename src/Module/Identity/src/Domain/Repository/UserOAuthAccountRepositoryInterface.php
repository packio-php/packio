<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Repository;

use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

interface UserOAuthAccountRepositoryInterface
{
    public function findById(string $id): ?UserOAuthAccount;

    public function findByProviderAndProviderUserId(OAuthProvider $oAuthProvider, string $providerUserId): ?UserOAuthAccount;

    /**
     * @return list<UserOAuthAccount>
     */
    public function findAllByUserId(string $userId): array;

    public function save(UserOAuthAccount $userOAuthAccount): void;

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void;
}
