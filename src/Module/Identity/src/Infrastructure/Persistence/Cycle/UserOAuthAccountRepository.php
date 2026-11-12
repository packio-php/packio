<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle;

use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

/**
 * @extends AbstractWriteRepository<UserOAuthAccount>
 */
final class UserOAuthAccountRepository extends AbstractWriteRepository implements UserOAuthAccountRepositoryInterface
{
    public function findById(string $id): ?UserOAuthAccount
    {
        $entity = $this->findByPK($id);

        return $entity instanceof UserOAuthAccount ? $entity : null;
    }

    public function findByProviderAndProviderUserId(OAuthProvider $oAuthProvider, string $providerUserId): ?UserOAuthAccount
    {
        $entity = $this->select()
            ->where('provider', $oAuthProvider->value)
            ->where('provider_user_id', $providerUserId)
            ->fetchOne()
        ;

        return $entity instanceof UserOAuthAccount ? $entity : null;
    }

    /**
     * @return list<UserOAuthAccount>
     */
    public function findAllByUserId(string $userId): array
    {
        return $this->select()
            ->where('user_id', $userId)
            ->fetchAll()
        ;
    }

    public function save(UserOAuthAccount $userOAuthAccount): void
    {
        $this->persistAndFlush($userOAuthAccount);
    }
}
