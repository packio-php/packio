<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Port;

use Packio\Organization\Api\Dto\OrganizationDeletionContext;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationResult;

interface OrganizationDeletionInitiationPort
{
    public function deleteOrganizationWithOutcome(
        string $organizationId,
        string $requestedByUserId = ''
    ): OrganizationDeletionInitiationResult;

    public function recordAudit(OrganizationDeletionContext $organizationDeletionContext, string $deletionRunId): bool;

    public function publishInitiationAfterAudit(string $organizationId, string $jobId): bool;

    public function revertInitiation(
        string $organizationId,
        bool $previousActive,
        ?string $jobId = null,
        ?string $deletionRunId = null
    ): bool;
}
