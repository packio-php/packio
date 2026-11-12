<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Identity\Api\Dto\IssuedRegistryAccessTokenDto;
use Packio\Identity\Api\Dto\RegistryAccessTokenDto;

final readonly class RegistryTokenResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(RegistryAccessTokenDto $registryAccessTokenDto): array
    {
        return [
            'id'           => $registryAccessTokenDto->id,
            'name'         => $registryAccessTokenDto->name,
            'permissions'  => $registryAccessTokenDto->permissions,
            'created_at'   => $registryAccessTokenDto->createdAt,
            'last_used_at' => '' !== $registryAccessTokenDto->lastUsedAt ? $registryAccessTokenDto->lastUsedAt : null,
            'masked_token' => $registryAccessTokenDto->maskedToken,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromIssued(IssuedRegistryAccessTokenDto $issuedRegistryAccessTokenDto): array
    {
        return [
            'id'           => $issuedRegistryAccessTokenDto->id,
            'name'         => $issuedRegistryAccessTokenDto->name,
            'permissions'  => $issuedRegistryAccessTokenDto->permissions,
            'created_at'   => $issuedRegistryAccessTokenDto->createdAt,
            'last_used_at' => null,
            'masked_token' => $issuedRegistryAccessTokenDto->maskedToken,
        ];
    }
}
