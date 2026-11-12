<?php

declare(strict_types=1);

namespace Packio\Shared\Api\Dto;

final readonly class UserDto
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public array $roles,
        public string $createdAt,
        public string $updatedAt = '',
        public bool $hasPassword = false,
        public bool $isEmailVerified = false,
        public bool $isBanned = false,
        public ?string $banReason = null,
        public ?string $bannedAt = null,
        public ?string $bannedByUserId = null,
    ) {}
}
