<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Port;

interface OrganizationDeletionJobStatusPort
{
    /**
     * @return list<array{id: string, type: string, status: string, error_message: ?string}>
     */
    public function runningOrPendingCleanupJobs(string $organizationId, string $deletionRunId): array;

    /**
     * @return list<array{id: string, type: string, status: string, error_message: ?string}>
     */
    public function failedCleanupJobs(string $organizationId, string $deletionRunId): array;
}
