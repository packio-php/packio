<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

final readonly class SourceProviderInstanceDto
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $providerType,
        public string $label,
        public string $baseUrl,
        public string $clientId,
        public bool $hasClientSecret,
        public string $authMode,
        public string $status,
        public array $metadata = [],
    ) {}
}
