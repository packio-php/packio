<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use LogicException;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceDeletionInitiationOutcome;
use Packio\Source\Api\Dto\SourceDeletionInitiationResult;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Port\AuditRecorderPort;
use Packio\Source\Application\Port\JobSchedulerPort;
use Packio\Source\Domain\Value\SourceDeletionInitiation;
use Psr\Log\LoggerInterface;
use Throwable;

use function is_string;
use function sprintf;
use function trim;

final readonly class SourceJobScheduler
{
    public function __construct(
        private SourceCommandService $sourceCommandService,
        private JobSchedulerPort $jobSchedulerPort,
        private AuditRecorderPort $auditRecorderPort,
        private LoggerInterface $logger,
    ) {}

    public function scheduleSourceDeleteInContext(ActorContext $actorContext, string $id): bool
    {
        $sourceDeletionInitiationResult = $this->completeSourceDeletionInitiation(
            $actorContext->organizationId,
            $id,
            $this->sourceCommandService->initiateDeletionInContext($actorContext, $id),
        );
        if (SourceDeletionInitiationOutcome::NotFound === $sourceDeletionInitiationResult->outcome) {
            return false;
        }

        return $this->auditAndPublishInitiation(
            $actorContext->organizationId,
            $id,
            $sourceDeletionInitiationResult,
            $actorContext->actorId,
            'organization-source-delete',
        );
    }

    public function scheduleSourceDelete(
        string $organizationId,
        string $id,
        ?string $deletionRunId = null,
        string $origin = '',
    ): bool {
        if (null === $deletionRunId || '' === trim($deletionRunId)) {
            $sourceDeletionInitiationResult = $this->initiateSourceDelete($organizationId, $id, $origin);
            if (SourceDeletionInitiationOutcome::NotFound === $sourceDeletionInitiationResult->outcome) {
                return false;
            }

            return $this->auditAndPublishInitiation(
                $organizationId,
                $id,
                $sourceDeletionInitiationResult,
                '',
                '' !== trim($origin) ? trim($origin) : 'source-delete',
            );
        }

        $resolvedDeletionRunId = trim($deletionRunId);
        if (! $this->sourceCommandService->ensureDeletionRun($id, $organizationId, $resolvedDeletionRunId)) {
            return false;
        }

        $resolvedOrigin        = trim($origin);
        $payload               = [
            'source_id'       => $id,
            'organization_id' => $organizationId,
        ];

        if ('' !== $resolvedDeletionRunId) {
            $payload['deletion_run_id'] = $resolvedDeletionRunId;
        }

        if ('' !== $resolvedOrigin) {
            $payload['origin'] = $resolvedOrigin;
        }

        $this->jobSchedulerPort->enqueueOnce(
            organizationId: $organizationId,
            type: 'source.delete',
            idempotencyKey: sprintf(
                'organization-delete:%s:%s:source:%s',
                $organizationId,
                $resolvedDeletionRunId,
                $id,
            ),
            payload: $payload,
            priority: 10,
        );

        return true;
    }

    public function scheduleSourceDeletionRecovery(
        string $organizationId,
        string $id,
        bool $force,
        string $origin = '',
    ): ?SourceDeletionInitiationResult {
        $deletionRunId = $this->sourceCommandService->recoverDeletionRun($id, $organizationId, $force);
        if (null === $deletionRunId) {
            return null;
        }

        $job = $this->jobSchedulerPort->enqueueOnce(
            organizationId: $organizationId,
            type: 'source.delete',
            idempotencyKey: sprintf('source-delete-recovery:%s:%s', $id, $deletionRunId),
            payload: [
                'source_id'       => $id,
                'organization_id' => $organizationId,
                'origin'          => trim($origin),
                'deletion_run_id' => $deletionRunId,
            ],
            priority: 10,
        );

        if (! $force && $job->terminal) {
            // A terminal recovery job is historical evidence, not an active
            // scheduler lease. Re-arm a fresh generation before creating the
            // next executable attempt so enqueueOnce cannot return it again.
            $deletionRunId = $this->sourceCommandService->recoverDeletionRun($id, $organizationId, true);
            if (null === $deletionRunId) {
                return null;
            }

            $job = $this->jobSchedulerPort->enqueueOnce(
                organizationId: $organizationId,
                type: 'source.delete',
                idempotencyKey: sprintf('source-delete-recovery:%s:%s', $id, $deletionRunId),
                payload: [
                    'source_id'       => $id,
                    'organization_id' => $organizationId,
                    'origin'          => trim($origin),
                    'deletion_run_id' => $deletionRunId,
                ],
                priority: 10,
            );
        }

        $this->logger->info('Source recovery lifecycle was scheduled by the owning scheduler.', [
            'source_id'       => $id,
            'organization_id' => $organizationId,
            'deletion_run_id' => $deletionRunId,
            'force'           => $force,
            'job_id'          => $job->id,
        ]);

        return new SourceDeletionInitiationResult(
            SourceDeletionInitiationOutcome::AlreadyDeleting,
            $job->id,
            deletionRunId: $deletionRunId,
        );
    }

    public function initiateSourceDelete(
        string $organizationId,
        string $id,
        string $origin = '',
    ): SourceDeletionInitiationResult {
        return $this->completeSourceDeletionInitiation(
            $organizationId,
            $id,
            $this->sourceCommandService->initiateDeletion($id, $organizationId),
            $origin,
        );
    }

    public function publishInitiationAfterAudit(string $organizationId, string $jobId): bool
    {
        return $this->jobSchedulerPort->publishAfterAudit($jobId, $organizationId);
    }

    public function confirmInitiationAudit(string $organizationId, string $jobId): bool
    {
        return $this->jobSchedulerPort->confirmAudit($jobId, $organizationId);
    }

    public function revertInitiation(
        string $organizationId,
        string $id,
        bool $previousActive,
        ?string $jobId = null,
        ?string $deletionRunId = null,
    ): bool {
        if (null === $jobId || '' === $jobId || ! $this->jobSchedulerPort->cancel($jobId, $organizationId)) {
            $this->logger->warning('Source deletion initiation compensation could not cancel the cleanup job.', [
                'source_id'       => $id,
                'organization_id' => $organizationId,
                'job_id'          => $jobId,
            ]);

            return false;
        }

        if (! is_string($deletionRunId) || '' === $deletionRunId) {
            $this->logger->error('Source deletion initiation compensation could not identify the lifecycle run.', [
                'source_id'       => $id,
                'organization_id' => $organizationId,
                'job_id'          => $jobId,
            ]);

            return false;
        }

        $this->sourceCommandService->revertInitiation($id, $organizationId, $previousActive, $deletionRunId);

        return true;
    }

    public function scheduleSourceSync(string $sourceId, string $organizationId, int $priority = 0): void
    {
        $this->jobSchedulerPort->enqueue(
            organizationId: $organizationId,
            type: 'source.sync',
            payload: [
                'source_id'       => $sourceId,
                'organization_id' => $organizationId,
            ],
            priority: $priority,
        );
    }

    public function scheduleSourceSyncOnce(string $sourceId, string $organizationId, string $idempotencyKey, int $priority = 0): string
    {
        return $this->jobSchedulerPort->enqueueSourceSyncOnce(
            organizationId: $organizationId,
            sourceId: $sourceId,
            idempotencyKey: $idempotencyKey,
            payload: [
                'source_id'       => $sourceId,
                'organization_id' => $organizationId,
            ],
            priority: $priority,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function scheduleSourceCreate(array $payload, string $organizationId, int $priority = 0): void
    {
        $this->jobSchedulerPort->enqueue(
            organizationId: $organizationId,
            type: 'source.create',
            payload: $payload,
            priority: $priority,
        );
    }

    private function completeSourceDeletionInitiation(
        string $organizationId,
        string $id,
        SourceDeletionInitiation $sourceDeletionInitiation,
        string $origin = '',
    ): SourceDeletionInitiationResult {
        $sourceDeletionInitiationOutcome    = SourceDeletionInitiationOutcome::from($sourceDeletionInitiation->outcome->value);

        if (SourceDeletionInitiationOutcome::NotFound === $sourceDeletionInitiationOutcome) {
            return new SourceDeletionInitiationResult($sourceDeletionInitiationOutcome);
        }

        if (SourceDeletionInitiationOutcome::AlreadyDeleting === $sourceDeletionInitiationOutcome) {
            $deletionRunId = $this->sourceCommandService->deletionRunId($id, $organizationId);
            if (null === $deletionRunId) {
                return new SourceDeletionInitiationResult($sourceDeletionInitiationOutcome);
            }

            if ('' === $deletionRunId) {
                throw new LogicException('Deleting source is missing its lifecycle run ID.');
            }

            $jobId = $this->ensureLifecycleJob($organizationId, $id, $deletionRunId, $origin);

            return new SourceDeletionInitiationResult(
                $sourceDeletionInitiationOutcome,
                $jobId,
                deletionRunId: $deletionRunId,
            );
        }

        $resolvedOrigin = trim($origin);
        $payload        = [
            'source_id'       => $id,
            'organization_id' => $organizationId,
        ];

        if ('' !== $resolvedOrigin) {
            $payload['origin'] = $resolvedOrigin;
        }

        try {
            $deletionRunId = $sourceDeletionInitiation->deletionRunId;
            if (null === $deletionRunId) {
                throw new LogicException('Initiated source deletion is missing its lifecycle run ID.');
            }

            $payload['deletion_run_id'] = $deletionRunId;
            $jobId                      = $this->jobSchedulerPort->enqueueOnceHeld(
                organizationId: $organizationId,
                type: 'source.delete',
                idempotencyKey: 'source-delete:' . $id . ':' . $deletionRunId,
                payload: $payload,
                priority: 10,
            );
        } catch (Throwable $throwable) {
            // The transition is durable. A retry reconstructs its exact job;
            // reverting here can race that recovery and orphan executable work.
            $this->logger->warning('Source lifecycle job enqueue failed; retry will recover the generation.', [
                'source_id'       => $id,
                'organization_id' => $organizationId,
                'deletion_run_id' => $sourceDeletionInitiation->deletionRunId,
            ]);

            throw $throwable;
        }

        return new SourceDeletionInitiationResult(
            SourceDeletionInitiationOutcome::Initiated,
            $jobId,
            $sourceDeletionInitiation->previousActive,
            $sourceDeletionInitiation->deletionRunId,
        );
    }

    private function auditAndPublishInitiation(
        string $organizationId,
        string $sourceId,
        SourceDeletionInitiationResult $sourceDeletionInitiationResult,
        string $actorId,
        string $origin,
    ): bool {
        if (null === $sourceDeletionInitiationResult->jobId || null === $sourceDeletionInitiationResult->deletionRunId) {
            return false;
        }

        $auditRecorded = $this->auditRecorderPort->recordOnce(
            operationKey: 'source-delete-initiation:' . $sourceId . ':' . $sourceDeletionInitiationResult->deletionRunId,
            event: 'source.delete.initiated',
            context: [
                'action_origin'   => $origin,
                'deletion_run_id' => $sourceDeletionInitiationResult->deletionRunId,
                'queued'          => true,
            ],
            organizationId: $organizationId,
            sourceId: $sourceId,
            actorId: $actorId,
            subjectType: 'source',
            subjectId: $sourceId,
        );

        $this->logger->info('Source deletion audit operation is durable.', [
            'organization_id'     => $organizationId,
            'source_id'           => $sourceId,
            'deletion_run_id'     => $sourceDeletionInitiationResult->deletionRunId,
            'audit_entry_created' => $auditRecorded,
        ]);

        return $this->publishInitiationAfterAudit($organizationId, $sourceDeletionInitiationResult->jobId);
    }

    /** @param non-empty-string $deletionRunId */
    private function ensureLifecycleJob(
        string $organizationId,
        string $sourceId,
        string $deletionRunId,
        string $origin
    ): string {
        $payload = [
            'source_id'       => $sourceId,
            'organization_id' => $organizationId,
            'deletion_run_id' => $deletionRunId,
        ];
        if ('' !== trim($origin)) {
            $payload['origin'] = trim($origin);
        }

        return $this->jobSchedulerPort->enqueueOnceHeld(
            organizationId: $organizationId,
            type: 'source.delete',
            idempotencyKey: 'source-delete:' . $sourceId . ':' . $deletionRunId,
            payload: $payload,
            priority: 10,
        );
    }
}
