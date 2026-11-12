<?php

declare(strict_types=1);

namespace Packio\Source\Application\Command;

final readonly class RegisterSourceCommand
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public string $organizationId,
        public string $provider,
        public string $resourceId,
        public string $name,
        public string $defaultRef = 'main',
        public string $visibility = 'public',
        public string $providerAuthorizationId = '',
        public string $providerInstanceId = '',
        public array $settings = [],
        public int $keepLastReleases = 0,
    ) {}
}
