<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Dto;

final readonly class SourceRegistrationData
{
    public function __construct(
        public string $provider,
        public string $organizationId,
        public string $resourceId,
        public string $name,
        public string $type,
        public string $url,
        public string $defaultBranch,
        public string $visibility,
        public ?string $authorizationId = null,
        public string $webhookSecret = '',
        public string $composerJsonPath = 'composer.json',
    ) {}
}
