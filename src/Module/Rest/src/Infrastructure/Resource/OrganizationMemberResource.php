<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Shared\Api\Dto\OrganizationMemberDto;

final readonly class OrganizationMemberResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(OrganizationMemberDto $organizationMemberDto): array
    {
        return [
            'id'              => $organizationMemberDto->id,
            'organization_id' => $organizationMemberDto->organizationId,
            'user_id'         => $organizationMemberDto->userId,
            'role'            => $organizationMemberDto->role,
            'created_at'      => '' !== $organizationMemberDto->createdAt ? $organizationMemberDto->createdAt : null,
            'updated_at'      => '' !== $organizationMemberDto->updatedAt ? $organizationMemberDto->updatedAt : null,
        ];
    }
}
