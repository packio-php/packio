<?php

declare(strict_types=1);

namespace Packio\Release\Api\Dto;

final readonly class ReleaseDto
{
    /**
     * @param array<string, mixed> $composer
     * @param array<string, mixed> $source
     * @param array<string, mixed> $dist
     */
    public function __construct(
        public string $packageName,
        public string $version,
        public string $reference,
        public array $composer,
        public array $source,
        public array $dist,
        public string $createdAt,
        public string $organizationId,
        public ?string $releasedAt = null,
        public bool $active = true,
        public int $downloadCount = 0,
        public ?string $upstreamReference = null,
        public ?string $referenceType = null,
    ) {}
}
