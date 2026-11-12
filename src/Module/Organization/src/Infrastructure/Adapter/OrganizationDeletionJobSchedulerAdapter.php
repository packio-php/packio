<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Adapter;

use Packio\Organization\Application\Port\OrganizationDeletionJobSchedulerPort;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Log\LoggerInterface;

use function in_array;
use function sprintf;
use function trim;

final readonly class OrganizationDeletionJobSchedulerAdapter implements OrganizationDeletionJobSchedulerPort
{
    private const int FINALIZE_MAX_ATTEMPTS = 50;

    public function __construct(
        private JobApiFacadeInterface $jobApiFacade,
        private LoggerInterface $logger,
    ) {}

    public function scheduleFinalize(string $organizationId, string $deletionRunId): bool
    {
        $this->jobApiFacade->enqueueOnce(
            organizationId: $organizationId,
            type: 'organization.delete',
            idempotencyKey: sprintf('organization-delete:%s:%s:finalize', $organizationId, $deletionRunId),
            payload: [
                'organization_id' => $organizationId,
                'deletion_run_id' => $deletionRunId,
                'stage'           => 'finalize',
            ],
            priority: 5,
            maxAttempts: self::FINALIZE_MAX_ATTEMPTS,
        );

        return true;
    }

    public function restartStage(string $organizationId, string $deletionRunId, string $stage, string $requestedByUserId): ?string
    {
        $terminalStageJob = null;
        foreach ($this->jobApiFacade->findByOrganizationDeletionRun(
            $organizationId,
            $deletionRunId,
            ['organization.delete'],
        ) as $activeJob) {
            if ($stage !== ($activeJob->payload['stage'] ?? null)) {
                continue;
            }

            if (in_array($activeJob->status, ['held', 'audited', 'pending', 'running'], true)) {
                return $activeJob->id;
            }

            if (
                in_array($activeJob->status, ['canceled', 'completed', 'failed'], true)
                && (
                    ! $terminalStageJob instanceof JobDto
                    || $activeJob->createdAt > $terminalStageJob->createdAt
                    || ($activeJob->createdAt === $terminalStageJob->createdAt && $activeJob->id > $terminalStageJob->id)
                )
            ) {
                $terminalStageJob = $activeJob;
            }
        }

        if (! $terminalStageJob instanceof JobDto) {
            return null;
        }

        $jobDto = $this->jobApiFacade->enqueueOnce(
            organizationId: $organizationId,
            type: 'organization.delete',
            // The exact terminal job deterministically identifies its single
            // successor attempt. Concurrent restarts therefore hit the same
            // unique enqueue key, while a terminal successor can be re-armed
            // again from its own ID.
            idempotencyKey: 'organization-delete-recovery:' . $organizationId . ':' . $deletionRunId . ':' . $stage . ':' . $terminalStageJob->id,
            payload: [
                'organization_id'      => $organizationId,
                'deletion_run_id'      => $deletionRunId,
                'stage'                => $stage,
                'requested_by_user_id' => trim($requestedByUserId),
            ],
            priority: 'finalize' === $stage ? 5 : 20,
        );

        $this->logger->info('Organization deletion stage was restarted by the owning scheduler.', [
            'organization_id'      => $organizationId,
            'deletion_run_id'      => $deletionRunId,
            'stage'                => $stage,
            'job_id'               => $jobDto->id,
            'requested_by_user_id' => trim($requestedByUserId),
        ]);

        return $jobDto->id;
    }
}
