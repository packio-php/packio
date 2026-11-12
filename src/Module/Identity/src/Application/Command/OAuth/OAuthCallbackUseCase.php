<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command\OAuth;

use Packio\Identity\Application\Result\OAuthCallbackResult;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Application\Result\OAuthUserResolutionType;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use RuntimeException;

use function trim;

final readonly class OAuthCallbackUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserOAuthAccountRepositoryInterface $userOAuthAccountRepository,
        private OAuthRegistrationPolicyInterface $oAuthRegistrationPolicy,
        private OAuthUserResolverInterface $oAuthUserResolver,
        private OAuthAccountLinkerInterface $oAuthAccountLinker,
        private OAuthCallbackTransactionInterface $oAuthCallbackTransaction,
    ) {}

    public function handle(ResolveOAuthCallbackCommand $resolveOAuthCallbackCommand): OAuthCallbackResult
    {
        return $this->oAuthCallbackTransaction->run(fn (): OAuthCallbackResult => $this->resolve($resolveOAuthCallbackCommand));
    }

    private function resolve(ResolveOAuthCallbackCommand $resolveOAuthCallbackCommand): OAuthCallbackResult
    {
        $data          = $resolveOAuthCallbackCommand->userData;
        $userCreated   = false;
        $linkedByEmail = false;
        if (null !== $resolveOAuthCallbackCommand->email) {
            $email = trim($resolveOAuthCallbackCommand->email);
            if ('' === $email) {
                throw new RuntimeException('Enter a valid email address.');
            }

            if ($this->userRepository->findByEmail($email) instanceof User) {
                throw new RuntimeException('An account with this email already exists. Sign in with that email first.');
            }
            $data = new OAuthUserData($data->providerUserId, $email, $data->username, $data->displayName, $data->avatarUrl);
        }

        if (null !== $resolveOAuthCallbackCommand->currentUserId) {
            $user = $this->userRepository->findById($resolveOAuthCallbackCommand->currentUserId);
            if (! $user instanceof User) {
                throw new RuntimeException('Current user not found');
            }
        } else {
            $account = $this->userOAuthAccountRepository->findByProviderAndProviderUserId($resolveOAuthCallbackCommand->provider, $data->providerUserId);
            if (! $account instanceof UserOAuthAccount && '' === trim((string) $data->email)) {
                return new OAuthCallbackResult(OAuthCallbackStatus::EmailRequired, pendingOAuth: $resolveOAuthCallbackCommand);
            }

            if (! $this->oAuthRegistrationPolicy->allowsRegistration($account, $data, $resolveOAuthCallbackCommand->registrationRedirectTarget)) {
                return new OAuthCallbackResult(OAuthCallbackStatus::RegistrationDisabled);
            }
            $resolution = $this->oAuthUserResolver->resolveUser($account, $data);
            $user       = $resolution->user;
            if (! $user instanceof User) {
                return new OAuthCallbackResult(OAuthCallbackStatus::EmailRequired, pendingOAuth: $resolveOAuthCallbackCommand);
            }
            $userCreated   = OAuthUserResolutionType::Created === $resolution->type;
            $linkedByEmail = OAuthUserResolutionType::ExistingEmail === $resolution->type;
        }

        if ($user->isBanned()) {
            return new OAuthCallbackResult(OAuthCallbackStatus::Banned, userId: $user->getIdentifier()->toString());
        }

        if (null === $resolveOAuthCallbackCommand->email && $resolveOAuthCallbackCommand->verifyEmailFromProvider && ! $user->isEmailVerified()) {
            if ($linkedByEmail) {
                // The password may have been set by someone who does not own this address.
                $user->updatePasswordHash('');
            }
            $user->markEmailAsVerified();
            $this->userRepository->save($user);
        }

        if (null === $resolveOAuthCallbackCommand->email) {
            $this->oAuthAccountLinker->persistOAuthAccount($user->getIdentifier()->toString(), $data, $resolveOAuthCallbackCommand->provider, $resolveOAuthCallbackCommand->scopes);
        }

        return new OAuthCallbackResult(
            null !== $resolveOAuthCallbackCommand->email ? OAuthCallbackStatus::VerificationRequired : OAuthCallbackStatus::Authenticated,
            $userCreated,
            $user->getIdentifier()->toString(),
            $user->email(),
            $user->displayName(),
        );
    }
}
