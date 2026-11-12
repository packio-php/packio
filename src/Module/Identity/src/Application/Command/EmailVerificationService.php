<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command;

use Cake\Chronos\Chronos;
use Common\Mail\MailerInterface;
use Packio\Identity\Application\Mail\AuthEmailTemplateRendererInterface;
use Packio\Identity\Application\Security\PasswordVerification;
use Packio\Identity\Domain\Entity\EmailVerificationCode;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\EmailVerificationCodeRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Throwable;

use function intdiv;
use function max;
use function password_hash;
use function random_int;
use function sprintf;

final readonly class EmailVerificationService
{
    public const int RESEND_COOLDOWN_SECONDS = 120;

    private const int CODE_TTL_SECONDS = 600;

    public function __construct(
        private EmailVerificationCodeRepositoryInterface $emailVerificationCodeRepository,
        private UserRepositoryInterface $userRepository,
        private MailerInterface $mailer,
        private AuthEmailTemplateRendererInterface $authEmailTemplateRenderer,
    ) {}

    public function sendVerificationCode(string $userUuid, bool $force = false): void
    {
        $user = $this->userRepository->findById($userUuid);
        if (! $user instanceof User || $user->isEmailVerified() || $user->isBanned()) {
            return;
        }

        $existingCode = $this->emailVerificationCodeRepository->findActiveByUserUuid($userUuid);
        if (
            $existingCode instanceof EmailVerificationCode
            && ! $existingCode->isExpired()
            && ! $force
        ) {
            return;
        }

        $previousCodeHash  = null;
        $previousExpiresAt = null;
        $createdNewCode    = false;

        if ($existingCode instanceof EmailVerificationCode && $force) {
            $previousCodeHash  = $existingCode->codeHash();
            $previousExpiresAt = $existingCode->expiresAt();
        }

        $code = $this->generateCode();

        $entity = $existingCode instanceof EmailVerificationCode
            ? $existingCode
            : new EmailVerificationCode();
        $entity->setUserUuid($userUuid);
        $entity->setCodeHash(password_hash($code, PASSWORD_DEFAULT));
        $entity->setExpiresAt(new Chronos('+10 minutes'));
        $createdNewCode = ! $existingCode instanceof EmailVerificationCode;

        $this->emailVerificationCodeRepository->save($entity);

        try {
            $email = $this->authEmailTemplateRenderer->renderEmailVerificationCode(
                code: $code,
                ttlMinutes: intdiv(self::CODE_TTL_SECONDS, 60),
            );

            $this->mailer->send($user->email(), $email->subject, $email->html);
        } catch (Throwable $e) {
            if ($createdNewCode) {
                $this->emailVerificationCodeRepository->delete($entity);
            } elseif ($force && null !== $previousCodeHash) {
                $entity->setCodeHash($previousCodeHash);
                $entity->setExpiresAt($previousExpiresAt);
                $this->emailVerificationCodeRepository->save($entity);
            }

            throw $e;
        }
    }

    public function verifyCode(string $userUuid, string $code): bool
    {
        $codeEntity = $this->emailVerificationCodeRepository->findActiveByUserUuid($userUuid);

        $isValid = PasswordVerification::matches($code, $codeEntity?->codeHash());
        if (! $codeEntity instanceof EmailVerificationCode || $codeEntity->isExpired() || ! $isValid) {
            return false;
        }

        $user = $this->userRepository->findById($userUuid);
        if (! $user instanceof User) {
            return false;
        }

        $user->markEmailAsVerified();
        $this->userRepository->save($user);

        $codeEntity->markAsVerified();
        $this->emailVerificationCodeRepository->save($codeEntity);

        return true;
    }

    public function remainingResendDelay(string $userUuid): int
    {
        $codeEntity = $this->emailVerificationCodeRepository->findActiveByUserUuid($userUuid);

        if (! $codeEntity instanceof EmailVerificationCode || $codeEntity->isExpired()) {
            return 0;
        }

        $sentAtTimestamp = $codeEntity->expiresAt()->getTimestamp() - self::CODE_TTL_SECONDS;
        $elapsedSeconds  = new Chronos()->getTimestamp() - $sentAtTimestamp;

        return max(0, self::RESEND_COOLDOWN_SECONDS - $elapsedSeconds);
    }

    private function generateCode(): string
    {
        return sprintf('%06d', random_int(0, 999999));
    }
}
