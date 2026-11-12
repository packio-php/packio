<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command;

use Cake\Chronos\Chronos;
use Packio\Identity\Application\Event\PasswordResetRequested;
use Packio\Identity\Application\Result\PasswordResetResult;
use Packio\Identity\Domain\Entity\PasswordResetToken;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\PasswordResetTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function bin2hex;
use function hash;
use function hash_equals;
use function random_bytes;
use function trim;

final readonly class PasswordResetService
{
    /**
     * @param array<string, mixed> $passwordResetConfig
     */
    public function __construct(
        private PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
        private UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $eventDispatcher,
        private array $passwordResetConfig,
    ) {}

    public function requestResetByEmail(string $email, ?string $ip = null, ?string $userAgent = null): void
    {
        $normalizedEmail = trim($email);
        if ('' === $normalizedEmail) {
            return;
        }

        $user = $this->userRepository->findByEmail($normalizedEmail);
        if (! $user instanceof User) {
            return;
        }

        $selector  = $this->generateSelector();
        $secret    = $this->generateSecret();
        $tokenHash = hash('sha256', $secret);

        $passwordResetToken = new PasswordResetToken(
            userUuid: $user->getIdentifier()->toString(),
            selector: $selector,
            tokenHash: $tokenHash,
            expiresAt: new Chronos('+' . $this->tokenTtlSeconds() . ' seconds'),
        );

        if (null !== $ip) {
            $passwordResetToken->setRequestedIp($ip);
        }

        if (null !== $userAgent) {
            $passwordResetToken->setUserAgent($userAgent);
        }

        $this->passwordResetTokenRepository->save($passwordResetToken);

        try {
            $this->eventDispatcher->dispatch(
                new PasswordResetRequested(
                    userUuid: $user->getIdentifier(),
                    userEmail: $user->email(),
                    selector: $selector,
                    secret: $secret,
                    requestedAt: new Chronos(),
                ),
            );
        } catch (Throwable $e) {
            $this->passwordResetTokenRepository->deleteAndFlush($passwordResetToken);

            throw $e;
        }

        $this->passwordResetTokenRepository->deleteActiveByUserUuid($user->getIdentifier()->toString(), $passwordResetToken->getIdentifier()->toString());
    }

    public function validateResetToken(string $selector, string $token): PasswordResetResult
    {
        $resetToken = $this->passwordResetTokenRepository->findBySelector($selector);
        if (! $resetToken instanceof PasswordResetToken) {
            return PasswordResetResult::invalidToken();
        }

        if ($resetToken->isUsed()) {
            return PasswordResetResult::usedToken();
        }

        if ($resetToken->isExpired()) {
            return PasswordResetResult::expiredToken();
        }

        $computedHash = hash('sha256', $token);
        if (! hash_equals($resetToken->tokenHash(), $computedHash)) {
            return PasswordResetResult::invalidToken();
        }

        return PasswordResetResult::success($resetToken->userUuid());
    }

    private function generateSelector(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function tokenTtlSeconds(): int
    {
        $ttl = (int) ($this->passwordResetConfig['token_ttl_seconds'] ?? 3600);

        return $ttl > 0 ? $ttl : 3600;
    }
}
