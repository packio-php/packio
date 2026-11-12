<?php

declare(strict_types=1);

namespace Packio\Organization\Api\Policy;

use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;

final class OrganizationPolicy
{
    public function canManage(?OrganizationDto $organizationDto, ?OrganizationMemberDto $organizationMemberDto): bool
    {
        return $organizationDto instanceof OrganizationDto
            && $organizationMemberDto instanceof OrganizationMemberDto
            && $organizationMemberDto->organizationId === $organizationDto->id
            && 'owner' === $organizationMemberDto->role;
    }
}
