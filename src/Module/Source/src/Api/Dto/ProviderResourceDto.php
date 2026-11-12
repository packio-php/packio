<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

final readonly class ProviderResourceDto
{
    public function __construct(
        public string $id,
        public string $provider,
        public string $name,
        public string $canonicalUrl,
        public string $defaultRef = 'main',
        public bool $private = false,
    ) {}
}
