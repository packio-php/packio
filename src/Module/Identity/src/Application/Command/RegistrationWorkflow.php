<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command;

use Packio\Identity\Application\Port\RegistrationPolicyPort;
use Packio\Identity\Application\Result\RegistrationResult;
use Packio\Identity\Application\Result\RegistrationStatus;
use Packio\Identity\Domain\EmailVerificationReason;
use Packio\Identity\Domain\Entity\User;
use Ramsey\Uuid\Uuid;

final readonly class RegistrationWorkflow
{
    public function __construct(
        private RegistrationPolicyPort $registrationPolicyPort,
        private IdentityCommandService $identityCommandService,
        private VerificationDispatch $verificationDispatch
    ) {}

    public function handle(
        string $email,
        string $displayName,
        string $password,
        string $redirectTo,
        bool $challengePassed
    ): RegistrationResult {
        if (! $this->registrationPolicyPort->allowsRegistration($redirectTo)) {
            return new RegistrationResult(RegistrationStatus::Disabled);
        }

        if (! $challengePassed) {
            return new RegistrationResult(RegistrationStatus::ChallengeFailed);
        }
        $user = $this->identityCommandService->registerUser($email, $displayName, $password);
        if (! $user instanceof User) {
            $this->verificationDispatch->request(Uuid::uuid4()->toString(), EmailVerificationReason::Registration);

            return new RegistrationResult(RegistrationStatus::EmailAlreadyExists);
        }
        $userId = $user->getIdentifier()->toString();
        $this->verificationDispatch->request($userId, EmailVerificationReason::Registration);

        return new RegistrationResult(RegistrationStatus::VerificationRequired, $userId);
    }
}
