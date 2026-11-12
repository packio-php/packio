<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth;

use Packio\Identity\Application\Command\OAuth\OAuthAccountLinkerInterface;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthUserData;

final readonly class OAuthAccountLinker implements OAuthAccountLinkerInterface
{
    public function __construct(private UserOAuthAccountRepositoryInterface $userOAuthAccountRepository) {}

    /**
     * @param list<string> $scopes
     */
    public function persistOAuthAccount(
        string $userId,
        OAuthUserData $oAuthUserData,
        OAuthProvider $oAuthProvider,
        array $scopes = [],
    ): void {
        $existingAccountByProvider = $this->userOAuthAccountRepository->findByProviderAndProviderUserId(
            $oAuthProvider,
            $oAuthUserData->providerUserId,
        );

        if ($existingAccountByProvider instanceof UserOAuthAccount) {
            // If this account belongs to a DIFFERENT user, don't create duplicate
            if ($existingAccountByProvider->userId() !== $userId) {
                return;
            }

            $existingAccountByProvider->updateScopes($scopes);
            $this->userOAuthAccountRepository->save($existingAccountByProvider);

            return;
        }

        // No existing account - create new one
        $userOAuthAccount = UserOAuthAccount::register(
            userId: $userId,
            oAuthProvider: $oAuthProvider,
            providerUserId: $oAuthUserData->providerUserId,
            providerEmail: $oAuthUserData->email,
            providerUsername: $oAuthUserData->username,
            scopes: $scopes,
        );

        $this->userOAuthAccountRepository->save($userOAuthAccount);
    }
}
