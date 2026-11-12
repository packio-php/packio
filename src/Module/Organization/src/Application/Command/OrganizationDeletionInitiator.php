<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Command;

use Packio\Organization\Api\Dto\OrganizationDeletionContext;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationResult;
use Packio\Organization\Application\Port\OrganizationDeletionInitiationPort;
use Psr\Log\LoggerInterface;
use Throwable;

use function in_array;

final readonly class OrganizationDeletionInitiator
{
    public function __construct(
        private OrganizationDeletionInitiationPort $organizationDeletionInitiationPort,
        private LoggerInterface $logger
    ) {}

    public function initiate(OrganizationDeletionContext $organizationDeletionContext): OrganizationDeletionInitiationResult
    {
        if (! in_array($organizationDeletionContext->origin, ['organization', 'admin', 'api'], true)) {
            return new OrganizationDeletionInitiationResult(OrganizationDeletionInitiationOutcome::Rejected);
        }
        $organizationId                       = $organizationDeletionContext->organizationId;
        $organizationDeletionInitiationResult = $this->organizationDeletionInitiationPort->deleteOrganizationWithOutcome($organizationId, $organizationDeletionContext->actorId);
        if (
            null === $organizationDeletionInitiationResult->jobId
            || null === $organizationDeletionInitiationResult->deletionRunId
        ) {
            return $organizationDeletionInitiationResult;
        }

        try {
            $auditRecorded = $this->organizationDeletionInitiationPort->recordAudit($organizationDeletionContext, $organizationDeletionInitiationResult->deletionRunId);
        } catch (Throwable $throwable) {
            $this->logger->error('Organization deletion audit failed before publication.', [
                'organization_id' => $organizationId,
                'deletion_run_id' => $organizationDeletionInitiationResult->deletionRunId,
                'job_id'          => $organizationDeletionInitiationResult->jobId,
            ]);

            if (OrganizationDeletionInitiationOutcome::Initiated === $organizationDeletionInitiationResult->outcome) {
                $this->organizationDeletionInitiationPort->revertInitiation(
                    $organizationId,
                    $organizationDeletionInitiationResult->previousActive,
                    $organizationDeletionInitiationResult->jobId,
                    $organizationDeletionInitiationResult->deletionRunId,
                );
            }

            throw $throwable;
        }

        $published = $this->organizationDeletionInitiationPort->publishInitiationAfterAudit(
            $organizationId,
            $organizationDeletionInitiationResult->jobId,
        );
        $this->logger->info('Organization deletion lifecycle passed its durable audit boundary.', [
            'organization_id'     => $organizationId,
            'deletion_run_id'     => $organizationDeletionInitiationResult->deletionRunId,
            'job_id'              => $organizationDeletionInitiationResult->jobId,
            'audit_entry_created' => $auditRecorded,
            'published'           => $published,
        ]);

        return new OrganizationDeletionInitiationResult(
            $published
                ? (OrganizationDeletionInitiationOutcome::Initiated === $organizationDeletionInitiationResult->outcome
                    ? OrganizationDeletionInitiationOutcome::Initiated : OrganizationDeletionInitiationOutcome::AlreadyInProgress)
                : OrganizationDeletionInitiationOutcome::PublicationFailed,
            $organizationDeletionInitiationResult->jobId,
            $organizationDeletionInitiationResult->previousActive,
            $organizationDeletionInitiationResult->deletionRunId,
        );
    }
}
