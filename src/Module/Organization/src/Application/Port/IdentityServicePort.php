<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Port;

interface IdentityServicePort
{
    public function revokeRegistryTokensForOrganization(string $organizationId): int;
}
