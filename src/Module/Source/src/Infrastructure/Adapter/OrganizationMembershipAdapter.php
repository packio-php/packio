<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter;

use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Source\Application\Dto\MembershipData;
use Packio\Source\Application\Port\OrganizationMembershipPort;

final readonly class OrganizationMembershipAdapter implements OrganizationMembershipPort
{
    public function __construct(private OrganizationApiFacadeInterface $organizationApiFacade) {}

    public function findMembership(string $organizationId, string $userId): ?MembershipData
    {
        $membership = $this->organizationApiFacade->findMembership($organizationId, $userId);

        return $membership instanceof OrganizationMemberDto
            ? new MembershipData(
                organizationId: $membership->organizationId,
                userId: $membership->userId,
                role: $membership->role,
            )
            : null;
    }
}
