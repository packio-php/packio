<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

final readonly class ProviderAuthorizationRequestDto
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $provider,
        public string $organizationId,
        public string $authStrategy = 'token',
        public array $payload = []
    ) {}
}
