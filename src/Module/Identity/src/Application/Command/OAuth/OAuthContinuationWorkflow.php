<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command\OAuth;

use Packio\Identity\Application\Command\EmailVerificationService;
use Packio\Identity\Application\Command\VerificationDispatch;
use Packio\Identity\Application\Result\OAuthCallbackResult;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Domain\EmailVerificationReason;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthUserData;

final readonly class OAuthContinuationWorkflow implements OAuthContinuationInterface
{
    public function __construct(
        private OAuthCallbackUseCase $oAuthCallbackUseCase,
        private UserRepositoryInterface $userRepository,
        private EmailVerificationService $emailVerificationService,
        private VerificationDispatch $verificationDispatch,
        private OAuthCallbackTransactionInterface $oAuthCallbackTransaction,
    ) {}

    public function submit(ResolveOAuthCallbackCommand $resolveOAuthCallbackCommand): OAuthCallbackResult
    {
        $oAuthCallbackResult = $this->oAuthCallbackUseCase->handle($resolveOAuthCallbackCommand);
        if (OAuthCallbackStatus::VerificationRequired === $oAuthCallbackResult->status && null !== $oAuthCallbackResult->userId) {
            $this->verificationDispatch->request($oAuthCallbackResult->userId, EmailVerificationReason::Registration);
        }

        return $oAuthCallbackResult;
    }

    public function verify(
        string $userId,
        string $code,
        ?ResolveOAuthCallbackCommand $resolveOAuthCallbackCommand,
        bool $oauthRequired
    ): OAuthCallbackResult {
        if ($oauthRequired && ! $resolveOAuthCallbackCommand instanceof ResolveOAuthCallbackCommand) {
            return new OAuthCallbackResult(OAuthCallbackStatus::SessionExpired);
        }

        return $this->oAuthCallbackTransaction->run(function() use ($userId, $code, $resolveOAuthCallbackCommand): OAuthCallbackResult {
            if (! $this->emailVerificationService->verifyCode($userId, $code)) {
                return new OAuthCallbackResult(OAuthCallbackStatus::InvalidCode);
            }

            $user = $this->userRepository->findById($userId);
            if (! $user instanceof User) {
                return new OAuthCallbackResult(OAuthCallbackStatus::SessionExpired);
            }

            if ($user->isBanned()) {
                return new OAuthCallbackResult(OAuthCallbackStatus::Banned, userId: $userId);
            }

            if (! $resolveOAuthCallbackCommand instanceof ResolveOAuthCallbackCommand) {
                return new OAuthCallbackResult(OAuthCallbackStatus::Authenticated, userId: $userId);
            }

            $data = $resolveOAuthCallbackCommand->userData;

            return $this->oAuthCallbackUseCase->handle(new ResolveOAuthCallbackCommand(
                $resolveOAuthCallbackCommand->provider,
                $resolveOAuthCallbackCommand->tokens,
                new OAuthUserData($data->providerUserId, $user->email(), $data->username, $data->displayName, $data->avatarUrl),
                $resolveOAuthCallbackCommand->scopes,
                currentUserId: $userId,
                verifyEmailFromProvider: false,
            ));
        });
    }
}
