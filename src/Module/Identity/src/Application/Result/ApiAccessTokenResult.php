<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

final readonly class ApiAccessTokenResult
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
}
