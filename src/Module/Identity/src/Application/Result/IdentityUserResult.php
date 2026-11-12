<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

final readonly class IdentityUserResult
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
        public string $updatedAt,
        public bool $hasPassword,
        public bool $isEmailVerified,
        public bool $isBanned = false,
        public ?string $banReason = null,
        public ?string $bannedAt = null,
        public ?string $bannedByUserId = null,
    ) {}
}
