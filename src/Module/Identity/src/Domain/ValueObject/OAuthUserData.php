<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\ValueObject;

final readonly class OAuthUserData
{
    public function __construct(
        public string $providerUserId,
        public ?string $email,
        public ?string $username,
        public ?string $displayName,
        public ?string $avatarUrl,
    ) {}
}
