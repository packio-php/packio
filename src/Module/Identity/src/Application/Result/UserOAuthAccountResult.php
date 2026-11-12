<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

final readonly class UserOAuthAccountResult
{
    public function __construct(
        public string $id,
        public string $provider,
        public ?string $providerUsername,
        public ?string $providerEmail
    ) {}
}
