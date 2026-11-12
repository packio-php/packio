<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Dto;

final readonly class SourceData
{
    /**
     * @param array<string, mixed> $providerConfig
     */
    public function __construct(
        public string $id,
        public string $provider,
        public string $organizationId,
        public string $owner,
        public string $repository,
        public bool $active,
        public string $visibility,
        public array $providerConfig = [],
        public string $authType = '',
    ) {}
}
