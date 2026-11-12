<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

final readonly class RegistryAccessTokenRegenerationDto
{
    private function __construct(
        public bool $success,
        public string $failureReason = '',
        public ?IssuedRegistryAccessTokenDto $issued = null,
        public ?string $revokedId = null,
    ) {}

    public static function regenerated(IssuedRegistryAccessTokenDto $issuedRegistryAccessTokenDto, string $revokedId): self
    {
        return new self(success: true, issued: $issuedRegistryAccessTokenDto, revokedId: $revokedId);
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
