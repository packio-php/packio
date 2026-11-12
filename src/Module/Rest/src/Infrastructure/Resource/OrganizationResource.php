<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Shared\Api\Dto\OrganizationDto;

final readonly class OrganizationResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(OrganizationDto $organizationDto): array
    {
        return [
            'id'               => $organizationDto->id,
            'slug'             => $organizationDto->slug,
            'name'             => $organizationDto->name,
            'anonymous_access' => $organizationDto->anonymousAccess,
            'active'           => $organizationDto->active,
            'status'           => $organizationDto->status,
            'created_at'       => '' !== $organizationDto->createdAt ? $organizationDto->createdAt : null,
            'updated_at'       => '' !== $organizationDto->updatedAt ? $organizationDto->updatedAt : null,
        ];
    }
}
