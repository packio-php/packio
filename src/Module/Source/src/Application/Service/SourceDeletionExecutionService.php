<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Dto\SourceDeletionExecutionResult;
use Packio\Source\Application\Exception\SourceDeletionExecutionLeaseLostException;
use Packio\Source\Application\Port\SourceDeleteArtifactsPort;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function trim;

final readonly class SourceDeletionExecutionService implements SourceDeletionExecutionInterface
{
    public function __construct(
        private SourceCommandService $sourceCommandService,
        private SourceDeleteArtifactsPort $sourceDeleteArtifactsPort,
        private SourceDeletionExecutionLeaseInterface $sourceDeletionExecutionLease,
        private SourceRepositoryInterface $sourceRepository,
    ) {}

    public function execute(
        string $sourceId,
        string $organizationId,
        string $deletionRunId,
        string $origin,
        LoggerInterface $jobLogger
    ): SourceDeletionExecutionResult {
        $deletionRunId = trim($deletionRunId);
        $context       = [
            'source_id'       => $sourceId,
            'organization_id' => $organizationId,
            'deletion_run_id' => $deletionRunId,
        ];
        if ('' === $deletionRunId) {
            $jobLogger->warning('Skipping source deletion without a lifecycle generation.', $context);

            return new SourceDeletionExecutionResult(SourceDeletionOutcome::StaleLease, [
                ...$context,
                'stale_generation' => true,
            ]);
        }

        $acquired = false;

        try {
            $acquired = $this->sourceDeletionExecutionLease->acquire($sourceId, $organizationId, $deletionRunId);
            if (! $acquired) {
                if (! $this->sourceRepository->findByIdInOrganization($sourceId, $organizationId) instanceof Source) {
                    return new SourceDeletionExecutionResult(SourceDeletionOutcome::AlreadyDeleted, [
                        ...$context,
                        'source_found' => false,
                        'deleted'      => true,
                    ]);
                }
                $jobLogger->warning('Skipping source deletion because the execution lease is unavailable or the generation is stale.', $context);

                return new SourceDeletionExecutionResult(SourceDeletionOutcome::StaleLease, [
                    ...$context,
                    'stale_generation' => true,
                ]);
            }

            $jobLogger->info('Source deletion execution lease acquired.', $context);
            $this->sourceDeletionExecutionLease->assertHeld($sourceId, $organizationId, $deletionRunId);
            $artifacts = $this->sourceDeleteArtifactsPort->deleteRelatedArtifacts($sourceId, $organizationId, $deletionRunId, $origin);
            foreach ($artifacts->archiveCleanupWarnings as $warning) {
                $jobLogger->warning('Archive cleanup could not be scheduled.', [...$context, ...$warning]);
            }

            $this->sourceDeletionExecutionLease->assertHeld($sourceId, $organizationId, $deletionRunId);
            $result = $this->sourceCommandService->deleteSourceAfterWebhookCleanup($sourceId, $organizationId, $deletionRunId);
            if ($result['source_found'] && ! $result['deleted']) {
                $acquired = false;
                $this->sourceDeletionExecutionLease->rollback($sourceId, $organizationId, $deletionRunId);

                return new SourceDeletionExecutionResult(SourceDeletionOutcome::Blocked, $result, 'Source could not be deleted.');
            }

            if ($result['deregistration_required']) {
                if ($result['deregistered']) {
                    $jobLogger->info('Webhook deregistered', $context);
                } else {
                    $jobLogger->warning('Webhook deregistration failed', $context);
                }
            }

            $this->sourceDeletionExecutionLease->commit($sourceId, $organizationId, $deletionRunId);
            $acquired = false;
            $outcome  = $result['source_found'] ? SourceDeletionOutcome::Deleted : SourceDeletionOutcome::AlreadyDeleted;
            $jobLogger->info('Source deletion completed', [
                ...$context,
                'outcome' => $outcome->value,
            ]);

            return new SourceDeletionExecutionResult($outcome, [
                ...$result,
                'artifacts' => $artifacts->toArray(),
            ]);
        } catch (Throwable $error) {
            if ($acquired) {
                $this->sourceDeletionExecutionLease->rollback($sourceId, $organizationId, $deletionRunId);
            }

            // A lost PostgreSQL session requires worker restart and reconciliation.
            // It must never be converted to an ordinary retry or a stale no-op.
            if ($error instanceof SourceDeletionExecutionLeaseLostException) {
                throw $error;
            }
            $jobLogger->error('Source deletion failed', [
                ...$context,
                'error' => $error->getMessage(),
            ]);

            return new SourceDeletionExecutionResult(SourceDeletionOutcome::RetryableFailure, $context, $error->getMessage());
        }
    }
}
