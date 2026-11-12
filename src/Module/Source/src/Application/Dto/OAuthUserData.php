<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

final readonly class OAuthUserData
{
    public function __construct(
        public string $providerUserId,
        public ?string $email = null,
        public ?string $username = null,
        public ?string $displayName = null,
        public ?string $avatarUrl = null,
    ) {}

    /**
     * @param array{provider_user_id: string, email: ?string, username: ?string, display_name: ?string, avatar_url: ?string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            providerUserId: $data['provider_user_id'],
            email: $data['email'] ?? null,
            username: $data['username'] ?? null,
            displayName: $data['display_name'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
        );
    }
}
