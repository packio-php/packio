<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

final readonly class ApiAccessTokenDto
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public string $id,
        public string $userId,
        public string $name,
        public string $tokenPrefix,
        public array $scopes,
        public string $createdAt,
        public string $updatedAt = '',
        public string $lastUsedAt = '',
        public string $expiresAt = '',
        public string $revokedAt = '',
    ) {}

    public function isRevoked(): bool
    {
        return '' !== $this->revokedAt;
    }
}
