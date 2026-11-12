<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

final readonly class RegistryAccessTokenDto
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
        public ?string $plainToken = null,
        public bool $isOwnerBanned = false,
    ) {}
}
