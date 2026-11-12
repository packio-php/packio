<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Port;

interface SourceDeletionSchedulerPort
{
    /** @return list<object{id: string, organizationId: string}> */
    public function sourcesForOrganization(string $organizationId): array;

    public function scheduleSourceDelete(string $sourceId, string $organizationId, string $deletionRunId): bool;
}
