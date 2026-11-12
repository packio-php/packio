<?php

declare(strict_types=1);

namespace Packio\Source\Application\Port;

use Packio\Source\Application\Dto\MembershipData;

interface OrganizationMembershipPort
{
    public function findMembership(string $organizationId, string $userId): ?MembershipData;
}
