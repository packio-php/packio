<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

final readonly class UserOAuthAccountDto
{
    public function __construct(
        public string $id,
        public string $provider,
        public string $providerLabel,
        public string $accountLabel,
        public ?string $providerEmail,
        public ?string $providerUsername,
    ) {}
}
