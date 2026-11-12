<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

final readonly class PasswordResetResult
{
    /**
     * @param list<string> $errors
     */
    private function __construct(
        public bool $success,
        public ?string $reason = null,
        public ?string $userUuid = null,
        public array $errors = []
    ) {}

    public static function success(string $userUuid): self
    {
        return new self(success: true, userUuid: $userUuid);
    }

    public static function userNotFound(): self
    {
        return new self(success: false, reason: 'user_not_found');
    }

    public static function invalidToken(): self
    {
        return new self(success: false, reason: 'invalid_token');
    }

    public static function expiredToken(): self
    {
        return new self(success: false, reason: 'expired_token');
    }

    public static function usedToken(): self
    {
        return new self(success: false, reason: 'used_token');
    }

    /**
     * @param list<string> $errors
     */
    public static function validationFailed(array $errors): self
    {
        return new self(
            success: false,
            reason: 'validation_failed',
            errors: $errors,
        );
    }

    public function isInvalidToken(): bool
    {
        return 'invalid_token' === $this->reason;
    }

    public function isExpiredToken(): bool
    {
        return 'expired_token' === $this->reason;
    }

    public function isUsedToken(): bool
    {
        return 'used_token' === $this->reason;
    }

    public function isValidationFailed(): bool
    {
        return 'validation_failed' === $this->reason;
    }
}
