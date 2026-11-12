<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth;

use Packio\Identity\Application\Command\OAuth\OAuthRegistrationPolicyInterface;
use Packio\Identity\Application\Port\RegistrationPolicyPort;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthUserData;

final readonly class OAuthRegistrationPolicy implements OAuthRegistrationPolicyInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RegistrationPolicyPort $registrationPolicyPort,
    ) {}

    public function allowsRegistration(?UserOAuthAccount $userOAuthAccount, OAuthUserData $oAuthUserData, string $redirectTo): bool
    {
        // If user already has OAuth account, allow login
        if ($userOAuthAccount instanceof UserOAuthAccount) {
            return true;
        }

        // If user exists by email, allow login
        $userByEmail = $this->userRepository->findByEmail($oAuthUserData->email ?? '');
        if ($userByEmail instanceof User) {
            return true;
        }

        return $this->registrationPolicyPort->allowsRegistration($redirectTo);
    }
}
