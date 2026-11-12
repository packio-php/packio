<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

final readonly class SourceOAuthAccountDto
{
    public function __construct(
        public string $id,
        public string $provider,
        public ?string $providerUsername,
        public ?string $providerEmail
    ) {}
}
