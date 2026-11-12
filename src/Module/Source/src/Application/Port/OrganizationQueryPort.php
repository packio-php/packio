<?php

declare(strict_types=1);

namespace Packio\Source\Application\Port;

interface OrganizationQueryPort
{
    /**
     * Resolve an organization slug to its UUID identifier.
     *
     * @return null|string the organization UUID, or null if no organization with the given slug exists
     */
    public function resolveOrganizationId(string $slug): ?string;
}
