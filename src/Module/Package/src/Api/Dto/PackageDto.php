<?php

declare(strict_types=1);

namespace Packio\Package\Api\Dto;

final readonly class PackageDto
{
    public function __construct(
        public string $name,
        public string $type,
        public string $description,
        public string $visibility,
        public string $sourceType,
        public string $sourceUrl,
        public string $createdAt,
        public string $updatedAt,
        public string $organizationId,
        public bool $active = true,
        public ?string $readmeHtml = null,
        public string $sourceId = '',
        public int $downloadCount = 0,
    ) {}
}
