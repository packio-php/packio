<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

final readonly class ApiAccessTokenRegenerationDto
{
    private function __construct(
        public bool $success,
        public string $failureReason = '',
        public ?IssuedApiAccessTokenDto $issued = null,
        public ?string $revokedId = null,
    ) {}

    public static function regenerated(IssuedApiAccessTokenDto $issuedApiAccessTokenDto, string $revokedId): self
    {
        return new self(success: true, issued: $issuedApiAccessTokenDto, revokedId: $revokedId);
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
