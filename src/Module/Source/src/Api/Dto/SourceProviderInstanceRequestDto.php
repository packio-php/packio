<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

final readonly class SourceProviderInstanceRequestDto
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $providerType,
        public string $label,
        public string $baseUrl,
        public string $clientId,
        public ?string $clientSecret,
        public string $authMode,
        public array $metadata = [],
    ) {}
}
