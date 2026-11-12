<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Dto;

final readonly class ProviderResourceData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public string $url,
        public string $defaultBranch,
        public ?string $description = null,
        public bool $private = false,
    ) {}
}
