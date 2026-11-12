<?php

declare(strict_types=1);

namespace Packio\Package\Api\Policy;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;

final readonly class PackagePolicy
{
    public function belongsToOrganization(?PackageDto $packageDto, ?OrganizationDto $organizationDto): bool
    {
        return $packageDto instanceof PackageDto
            && $organizationDto instanceof OrganizationDto
            && $packageDto->organizationId === $organizationDto->id;
    }

    public function canManage(
        ?PackageDto $packageDto,
        ?OrganizationDto $organizationDto,
        ?OrganizationMemberDto $organizationMemberDto
    ): bool {
        if (! $organizationDto instanceof OrganizationDto) {
            return false;
        }

        return $this->belongsToOrganization($packageDto, $organizationDto)
            && $organizationMemberDto instanceof OrganizationMemberDto
            && $organizationMemberDto->organizationId === $organizationDto->id
            && 'owner' === $organizationMemberDto->role;
    }
}
