<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth;

use Packio\Identity\Application\Command\IdentityCommandService;
use Packio\Identity\Application\Command\OAuth\OAuthUserResolverInterface;
use Packio\Identity\Application\Result\OAuthUserResolution;
use Packio\Identity\Application\Result\OAuthUserResolutionType;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use RuntimeException;

use function trim;

final readonly class OAuthUserResolver implements OAuthUserResolverInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private IdentityCommandService $identityCommandService,
    ) {}

    public function resolveUser(?UserOAuthAccount $userOAuthAccount, OAuthUserData $oAuthUserData): OAuthUserResolution
    {
        if ($userOAuthAccount instanceof UserOAuthAccount) {
            $user = $this->userRepository->findById($userOAuthAccount->userId());
            if (! $user instanceof User) {
                throw new RuntimeException('OAuth account references non-existent user');
            }

            return new OAuthUserResolution(OAuthUserResolutionType::Linked, $user);
        }

        if ('' === trim((string) $oAuthUserData->email)) {
            return new OAuthUserResolution(OAuthUserResolutionType::EmailRequired);
        }

        $userByEmail = $this->userRepository->findByEmail($oAuthUserData->email ?? '');
        if ($userByEmail instanceof User) {
            return new OAuthUserResolution(OAuthUserResolutionType::ExistingEmail, $userByEmail);
        }

        $email       = $oAuthUserData->email ?? '';
        $displayName = $oAuthUserData->displayName ?? $oAuthUserData->username ?? 'User';

        $user = $this->identityCommandService->createUserWithoutPassword($email, $displayName);

        return new OAuthUserResolution(OAuthUserResolutionType::Created, $user);
    }
}
