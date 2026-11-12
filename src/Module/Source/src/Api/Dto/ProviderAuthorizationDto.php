<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

final readonly class ProviderAuthorizationDto
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $provider,
        public string $authStrategy,
        public string $status,
        public string $accountName = '',
        public bool $configured = false,
        public string $maskedValue = '',
        public ?string $sourceOAuthAccountId = null,
        public ?string $providerInstanceId = null,
        public ?string $authHealth = null,
        public ?string $authFailureReason = null,
        public ?string $lastTokenRefreshAt = null,
    ) {}
}
