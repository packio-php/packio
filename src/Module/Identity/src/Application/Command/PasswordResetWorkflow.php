<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command;

use Packio\Identity\Application\Result\PasswordResetResult;
use Packio\Identity\Application\Validator\PasswordValidator;
use Packio\Identity\Domain\Entity\PasswordResetToken;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\PasswordResetTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;

use function password_hash;

final readonly class PasswordResetWorkflow
{
    public function __construct(
        private PasswordResetService $passwordResetService,
        private PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
        private UserRepositoryInterface $userRepository,
        private PasswordValidator $passwordValidator,
    ) {}

    public function handle(string $selector, string $token, string $newPassword, ?string $consumedIp = null): PasswordResetResult
    {
        $passwordResetResult = $this->passwordResetService->validateResetToken($selector, $token);
        if (! $passwordResetResult->success) {
            return $passwordResetResult;
        }

        $passwordValidationResult = $this->passwordValidator->validate($newPassword);
        if (! $passwordValidationResult->isValid) {
            return PasswordResetResult::validationFailed($passwordValidationResult->errors);
        }

        $resetToken = $this->passwordResetTokenRepository->findActiveBySelector($selector);
        if (! $resetToken instanceof PasswordResetToken) {
            return PasswordResetResult::invalidToken();
        }

        $user = $this->userRepository->findById($resetToken->userUuid());
        if (! $user instanceof User) {
            return PasswordResetResult::userNotFound();
        }

        $user->updatePasswordHash(password_hash($newPassword, PASSWORD_DEFAULT));
        $this->userRepository->save($user);

        $resetToken->markAsUsed();
        if (null !== $consumedIp) {
            $resetToken->setConsumedIp($consumedIp);
        }

        $this->passwordResetTokenRepository->save($resetToken);

        $this->passwordResetTokenRepository->deleteActiveByUserUuid($resetToken->userUuid());

        return PasswordResetResult::success($resetToken->userUuid());
    }
}
