<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

final readonly class SourceRegistrationRequestDto
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public string $provider,
        public string $resource,
        public string $organizationId,
        public string $defaultRef = 'main',
        public string $visibility = 'public',
        public string $providerAuthorizationId = '',
        public array $settings = [],
        public int $keepLastReleases = 0,
    ) {}
}
