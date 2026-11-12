<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command;

use Packio\Identity\Application\Result\IdentityUserResult;
use Packio\Identity\Application\Result\SignInResult;
use Packio\Identity\Application\Result\SignInStatus;
use Packio\Identity\Domain\EmailVerificationReason;

final readonly class SignInWorkflow
{
    public function __construct(
        private IdentityCredentialsInterface $identityCredentials,
        private VerificationDispatch $verificationDispatch
    ) {}

    public function handle(string $email, string $password): SignInResult
    {
        $user = $this->identityCredentials->authenticateCredentials($email, $password);
        if (! $user instanceof IdentityUserResult) {
            return new SignInResult(SignInStatus::InvalidCredentials);
        }

        if ($user->isBanned) {
            return new SignInResult(SignInStatus::Banned, $user->id);
        }

        if (! $user->isEmailVerified) {
            $this->verificationDispatch->request($user->id, EmailVerificationReason::Login);

            return new SignInResult(SignInStatus::VerificationRequired, $user->id);
        }

        return new SignInResult(SignInStatus::Authenticated, $user->id);
    }
}
