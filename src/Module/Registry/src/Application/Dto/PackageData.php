<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Dto;

final readonly class PackageData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public string $sourceId,
        public string $organizationId,
        public bool $active,
        public string $visibility,
        public ?string $description,
        public string $sourceType = '',
        public string $sourceUrl = '',
    ) {}
}
