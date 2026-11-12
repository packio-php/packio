<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Port;

interface OrganizationDeletionJobSchedulerPort
{
    public function scheduleFinalize(string $organizationId, string $deletionRunId): bool;

    /** Returns the active or newly queued stage job id, or null when the stage cannot be restarted. */
    public function restartStage(string $organizationId, string $deletionRunId, string $stage, string $requestedByUserId): ?string;
}
