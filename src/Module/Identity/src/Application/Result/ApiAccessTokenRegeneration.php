<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

final readonly class ApiAccessTokenRegeneration
{
    private function __construct(
        public bool $success,
        public ?string $failureReason = null,
        public ?IssuedApiAccessToken $issued = null,
        public ?string $revokedId = null,
    ) {}

    public static function regenerated(IssuedApiAccessToken $issuedApiAccessToken, string $revokedId): self
    {
        return new self(success: true, issued: $issuedApiAccessToken, revokedId: $revokedId);
    }

    public static function notFound(): self
    {
        return new self(success: false, failureReason: 'not_found');
    }

    public static function persistenceFailed(): self
    {
        return new self(success: false, failureReason: 'persistence_failed');
    }

    public function isNotFound(): bool
    {
        return 'not_found' === $this->failureReason;
    }

    public function isPersistenceFailed(): bool
    {
        return 'persistence_failed' === $this->failureReason;
    }
}
