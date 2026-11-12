<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Adapter;

use LogicException;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Organization\Api\Dto\OrganizationDeletionContext;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationResult;
use Packio\Organization\Application\Command\OrganizationCommandService;
use Packio\Organization\Application\Port\OrganizationDeletionInitiationPort;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function trim;

final readonly class OrganizationDeletionInitiationAdapter implements OrganizationDeletionInitiationPort
{
    public function __construct(
        private OrganizationCommandService $organizationCommandService,
        private JobApiFacadeInterface $jobApiFacade,
        private AuditApiFacadeInterface $auditApiFacade,
        private LoggerInterface $logger,
    ) {}

    public function deleteOrganizationWithOutcome(
        string $organizationId,
        string $requestedByUserId = '',
    ): OrganizationDeletionInitiationResult {
        if ('' === trim($organizationId)) {
            return new OrganizationDeletionInitiationResult(OrganizationDeletionInitiationOutcome::NotFound);
        }

        $organizationDeletionInitiation           = $this->organizationCommandService->initiateDeletion($organizationId);
        $organizationDeletionInitiationOutcome    = OrganizationDeletionInitiationOutcome::from($organizationDeletionInitiation->outcome->value);

        if (OrganizationDeletionInitiationOutcome::NotFound === $organizationDeletionInitiationOutcome) {
            return new OrganizationDeletionInitiationResult($organizationDeletionInitiationOutcome);
        }

        if (OrganizationDeletionInitiationOutcome::AlreadyDeleting === $organizationDeletionInitiationOutcome) {
            $deletionRunId = $this->organizationCommandService->ensureDeletionRun($organizationId);
            if (null === $deletionRunId) {
                return new OrganizationDeletionInitiationResult($organizationDeletionInitiationOutcome);
            }

            $job = $this->ensureLifecycleJob($organizationId, $deletionRunId, $requestedByUserId);

            return new OrganizationDeletionInitiationResult(
                $organizationDeletionInitiationOutcome,
                $job->id,
                deletionRunId: $deletionRunId,
            );
        }

        try {
            $deletionRunId = $organizationDeletionInitiation->deletionRunId;
            if (null === $deletionRunId) {
                throw new LogicException('Initiated organization deletion is missing its lifecycle run ID.');
            }

            $job = $this->jobApiFacade->enqueueOnceHeld(
                organizationId: $organizationId,
                type: 'organization.delete',
                idempotencyKey: 'organization-delete:' . $organizationId . ':' . $deletionRunId,
                payload: [
                    'organization_id'      => $organizationId,
                    'requested_by_user_id' => trim($requestedByUserId),
                    'deletion_run_id'      => $deletionRunId,
                    'stage'                => 'schedule_cleanup',
                ],
                priority: 20,
            );
        } catch (Throwable $throwable) {
            // The transition is durable. A retry reconstructs its exact job;
            // reverting here can race that recovery and orphan executable work.
            $this->logger->warning('Organization lifecycle job enqueue failed; retry will recover the generation.', [
                'organization_id' => $organizationId,
                'deletion_run_id' => $organizationDeletionInitiation->deletionRunId,
            ]);

            throw $throwable;
        }

        return new OrganizationDeletionInitiationResult(
            OrganizationDeletionInitiationOutcome::Initiated,
            $job->id,
            $organizationDeletionInitiation->previousActive,
            $organizationDeletionInitiation->deletionRunId,
        );
    }

    public function revertInitiation(
        string $organizationId,
        bool $previousActive,
        ?string $jobId = null,
        ?string $deletionRunId = null,
    ): bool {
        if (null === $jobId || '' === $jobId || ! $this->jobApiFacade->cancel($jobId, $organizationId)) {
            $this->logger->warning('Organization deletion initiation compensation could not cancel the cleanup job.', [
                'organization_id' => $organizationId,
                'job_id'          => $jobId,
            ]);

            return false;
        }

        if (null === $deletionRunId || '' === $deletionRunId) {
            $this->logger->error('Organization deletion initiation compensation is missing the lifecycle run.', [
                'organization_id' => $organizationId,
                'job_id'          => $jobId,
            ]);

            return false;
        }

        $this->organizationCommandService->revertInitiation($organizationId, $previousActive, $deletionRunId);

        return true;
    }

    public function recordAudit(OrganizationDeletionContext $organizationDeletionContext, string $deletionRunId): bool
    {
        return $this->auditApiFacade->recordOnce(
            operationKey: ('admin' === $organizationDeletionContext->origin ? 'organization-delete-admin-initiation:' : 'organization-delete-initiation:') . $organizationDeletionContext->organizationId . ':' . $deletionRunId,
            event: $organizationDeletionContext->auditEvent,
            context: [
                'action_origin'   => match ($organizationDeletionContext->origin) {
                    'admin' => 'admin-organization-delete',
                    'api'   => 'api-organization-delete',
                    default => 'organization-delete',
                },
                'deletion_run_id' => $deletionRunId,
                'queued'          => true,
            ],
            organizationId: $organizationDeletionContext->organizationId,
            actorId: trim($organizationDeletionContext->actorId),
            subjectType: 'organization',
            subjectId: $organizationDeletionContext->organizationId,
        );
    }

    public function publishInitiationAfterAudit(string $organizationId, string $jobId): bool
    {
        return $this->jobApiFacade->publishAfterAudit($jobId, $organizationId);
    }

    private function ensureLifecycleJob(string $organizationId, string $deletionRunId, string $requestedByUserId = ''): JobDto
    {
        return $this->jobApiFacade->enqueueOnceHeld(
            organizationId: $organizationId,
            type: 'organization.delete',
            idempotencyKey: 'organization-delete:' . $organizationId . ':' . $deletionRunId,
            payload: [
                'organization_id'      => $organizationId,
                'requested_by_user_id' => trim($requestedByUserId),
                'deletion_run_id'      => $deletionRunId,
                'stage'                => 'schedule_cleanup',
            ],
            priority: 20,
        );
    }
}
