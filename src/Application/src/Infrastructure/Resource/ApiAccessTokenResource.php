<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Resource;

use Packio\Identity\Api\Dto\ApiAccessTokenDto;

final readonly class ApiAccessTokenResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(ApiAccessTokenDto $apiAccessTokenDto): array
    {
        return [
            'id'           => $apiAccessTokenDto->id,
            'name'         => $apiAccessTokenDto->name,
            'token_prefix' => $apiAccessTokenDto->tokenPrefix,
            'scopes'       => $apiAccessTokenDto->scopes,
            'created_at'   => $apiAccessTokenDto->createdAt,
            'updated_at'   => '' !== $apiAccessTokenDto->updatedAt ? $apiAccessTokenDto->updatedAt : null,
            'last_used_at' => '' !== $apiAccessTokenDto->lastUsedAt ? $apiAccessTokenDto->lastUsedAt : null,
            'expires_at'   => '' !== $apiAccessTokenDto->expiresAt ? $apiAccessTokenDto->expiresAt : null,
            'revoked_at'   => '' !== $apiAccessTokenDto->revokedAt ? $apiAccessTokenDto->revokedAt : null,
            'revoked'      => $apiAccessTokenDto->isRevoked(),
        ];
    }
}
