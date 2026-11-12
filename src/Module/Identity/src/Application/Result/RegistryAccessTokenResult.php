<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

final readonly class RegistryAccessTokenResult
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $permissions,
        public string $createdAt,
        public string $lastUsedAt,
        public string $organizationId,
        public string $maskedToken,
        public string $plainToken,
        public bool $isOwnerBanned = false,
    ) {}
}
