<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\SourceDeletion;

use Common\Logging\SafeExceptionContext;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Source\Api\Dto\SourceDeletionInitiationOutcome;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

use function array_any;
use function array_filter;
use function array_keys;
use function array_values;
use function bin2hex;
use function count;
use function in_array;
use function is_scalar;
use function random_bytes;
use function sprintf;
use function trim;

final readonly class SourceDeletionRecoveryService
{
    private const string RECOVERY_ORIGIN    = 'admin-source-delete-recovery';
    private const string INITIATION_ORIGIN  = 'admin-source-delete-initiation';

    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private JobApiFacadeInterface $jobApiFacade,
        private PackageApiFacadeInterface $packageApiFacade,
        private ReleaseApiFacadeInterface $releaseApiFacade,
        private ArchiveStorageCleanupApiFacadeInterface $archiveStorageCleanupApiFacade,
        private WebhookApiFacadeInterface $webhookApiFacade,
        private AuditApiFacadeInterface $auditApiFacade,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param list<SourceDto> $sources
     *
     * @return array<string, SourceDeletionRecoverySummary>
     */
    public function summariesForSources(array $sources): array
    {
        $deletingSources = [];
        foreach ($sources as $source) {
            if ('deleting' === $source->status) {
                $deletingSources[$source->id] = $source;
            }
        }

        if ([] === $deletingSources) {
            return [];
        }

        $packagesBySource = $this->packagesBySource($deletingSources);
        $releaseCounts    = [];
        foreach ($packagesBySource as $sourceId => $packages) {
            $releaseCounts[$sourceId] = $this->releaseCount($packages);
        }

        $summaries = [];
        foreach ($deletingSources as $sourceId => $source) {
            $summaries[$sourceId] = $this->buildSummary(
                $source,
                $this->jobApiFacade->findSourceDeleteJobs($source->organizationId, $sourceId),
                count($packagesBySource[$sourceId] ?? []),
                $releaseCounts[$sourceId] ?? 0,
            );
        }

        return $summaries;
    }

    public function summaryForSource(SourceDto $sourceDto): SourceDeletionRecoverySummary
    {
        return $this->summaryForSourcePackages($sourceDto, $this->packagesForSource($sourceDto));
    }

    /**
     * Starts the staged source deletion flow for a source that is not already
     * deleting. The same `source.delete` job type and idempotency key are used
     * as for organization-scoped deletion, so both initiation paths share one
     * cleanup flow.
     */
    public function initiateDeletion(string $sourceId, string $adminUserId): string
    {
        $source = $this->sourceApiFacade->findSource($sourceId);
        if (! $source instanceof SourceDto) {
            $this->logger->warning('Admin source deletion initiation was rejected: source not found.', [
                'admin_user_id' => $adminUserId,
                'source_id'     => $sourceId,
                'outcome'       => 'rejected',
                'origin'        => self::INITIATION_ORIGIN,
            ]);

            throw new SourceDeletionRecoveryException('Source not found.');
        }

        $packages     = $this->packagesForSource($source);
        $releaseCount = $this->releaseCount($packages);

        $sourceDeletionInitiationResult = $this->sourceApiFacade->initiateSourceDelete(
            $source->organizationId,
            $source->id,
            self::INITIATION_ORIGIN,
        );

        if (SourceDeletionInitiationOutcome::NotFound === $sourceDeletionInitiationResult->outcome) {
            $this->logger->warning('Admin source deletion initiation was rejected: source not found.', [
                'admin_user_id' => $adminUserId,
                'source_id'     => $source->id,
                'outcome'       => 'not_found',
                'origin'        => self::INITIATION_ORIGIN,
            ]);

            throw new SourceDeletionRecoveryException('Source not found.');
        }

        $deletionRunId = $sourceDeletionInitiationResult->deletionRunId;
        if (null === $deletionRunId || null === $sourceDeletionInitiationResult->jobId) {
            throw new SourceDeletionRecoveryException('Source deletion lifecycle could not be resumed.');
        }

        // The lifecycle job is held while audit is recorded. It can become
        // executable only after the audit write succeeds.
        try {
            $auditRecorded = $this->auditApiFacade->recordOnce(
                operationKey: 'source-delete-initiation:' . $source->id . ':' . $deletionRunId,
                event: 'source.delete.initiated_by_admin',
                context: [
                    'admin_user_id'         => $adminUserId,
                    'source_id'             => $source->id,
                    'organization_id'       => $source->organizationId,
                    'linked_packages_count' => count($packages),
                    'linked_releases_count' => $releaseCount,
                    'job_idempotency_key'   => 'source-delete:' . $source->id . ':' . $deletionRunId,
                    'action_origin'         => self::INITIATION_ORIGIN,
                ],
                organizationId: $source->organizationId,
                sourceId: $source->id,
                actorId: $adminUserId,
            );
        } catch (Throwable $throwable) {
            $this->logger->error('Source deletion initiation audit failed; compensating the initiation.', [
                'admin_user_id'   => $adminUserId,
                'source_id'       => $source->id,
                'organization_id' => $source->organizationId,
                'job_id'          => $sourceDeletionInitiationResult->jobId ?? '',
            ]);

            if (SourceDeletionInitiationOutcome::Initiated === $sourceDeletionInitiationResult->outcome) {
                $this->sourceApiFacade->revertInitiation(
                    $source->organizationId,
                    $source->id,
                    $sourceDeletionInitiationResult->previousActive,
                    $sourceDeletionInitiationResult->jobId,
                    $deletionRunId,
                );
            }

            throw $throwable;
        }

        if (! $this->sourceApiFacade->publishInitiationAfterAudit(
            $source->organizationId,
            $sourceDeletionInitiationResult->jobId,
        )) {
            throw new RuntimeException('Source deletion cleanup job could not be published after audit recording.');
        }

        $this->logger->info('Source deletion lifecycle is executable.', [
            'admin_user_id'       => $adminUserId,
            'source_id'           => $source->id,
            'organization_id'     => $source->organizationId,
            'outcome'             => 'queued',
            'origin'              => self::INITIATION_ORIGIN,
            'deletion_run_id'     => $deletionRunId,
            'audit_entry_created' => $auditRecorded,
        ]);

        return SourceDeletionInitiationOutcome::Initiated === $sourceDeletionInitiationResult->outcome
            ? 'Source deletion was initiated.'
            : 'Source deletion lifecycle was resumed.';
    }

    /**
     * Safe dependency preview for a source that is not deleting yet.
     *
     * @return array{
     *     source: array{id: string, name: string, provider: string, organization_id: string},
     *     packages_count: int,
     *     releases_count: int
     * }
     */
    public function deletionPreview(SourceDto $sourceDto): array
    {
        $packages = $this->packagesForSource($sourceDto);

        return [
            'source'         => [
                'id'              => $sourceDto->id,
                'name'            => '' !== $sourceDto->displayName
                    ? $sourceDto->displayName
                    : $sourceDto->owner . '/' . $sourceDto->repository,
                'provider'        => $sourceDto->provider,
                'organization_id' => $sourceDto->organizationId,
            ],
            'packages_count' => count($packages),
            'releases_count' => $this->releaseCount($packages),
        ];
    }

    public function requeueDelete(string $sourceId, string $adminUserId): string
    {
        $sourceDto                     = $this->requireDeletingSource($sourceId);
        $sourceDeletionRecoverySummary = $this->summaryForSource($sourceDto);
        $existingJob                   = $this->activeDeleteJob($sourceDeletionRecoverySummary->relatedJobs);
        if ($existingJob instanceof JobDto && 'held' === $existingJob->status) {
            throw new SourceDeletionRecoveryException('Source deletion is awaiting audit completion; cancel it or retry the initiating request.');
        }

        $createdJob      = ! $existingJob instanceof JobDto;
        $jobDto          = $existingJob;
        $deletionRunId   = null;
        if (! $jobDto instanceof JobDto) {
            $sourceDeletionInitiationResult = $this->sourceApiFacade->scheduleSourceDeletionRecovery(
                $sourceDto->organizationId,
                $sourceDto->id,
                false,
                self::RECOVERY_ORIGIN,
            );
            if (null === $sourceDeletionInitiationResult?->jobId || null === $sourceDeletionInitiationResult->deletionRunId) {
                throw new SourceDeletionRecoveryException('Source deletion lifecycle could not be recovered.');
            }

            $jobDto = $this->jobApiFacade->findById($sourceDeletionInitiationResult->jobId);
            if (! $jobDto instanceof JobDto) {
                throw new SourceDeletionRecoveryException('Source deletion recovery job was not found after scheduling.');
            }

            $deletionRunId = $sourceDeletionInitiationResult->deletionRunId;
        }
        $idempotencyKey = $jobDto->idempotencyKey ?? '';

        if ('audited' === $jobDto->status && ! $this->jobApiFacade->publish($jobDto->id, $sourceDto->organizationId)) {
            throw new SourceDeletionRecoveryException('Audited source deletion job could not be published.');
        }

        $this->recordAudit(
            'source.delete.requeued_by_admin',
            $sourceDeletionRecoverySummary,
            $adminUserId,
            [
                'new_job_id'      => $jobDto->id,
                'job_created'     => $createdJob,
                'idempotency_key' => $idempotencyKey,
                'deletion_run_id' => $deletionRunId,
                'action_origin'   => self::RECOVERY_ORIGIN,
            ],
        );

        return $createdJob
            ? 'Source deletion was requeued.'
            : (in_array($jobDto->status, ['held', 'pending', 'running'], true)
                ? 'Source already has an active delete job.'
                : 'Existing source deletion job was returned.');
    }

    public function forceRequeueDelete(string $sourceId, string $adminUserId): string
    {
        $sourceDto                      = $this->requireDeletingSource($sourceId);
        $sourceDeletionRecoverySummary  = $this->summaryForSource($sourceDto);
        if (! $sourceDeletionRecoverySummary->canForceRequeue) {
            throw new SourceDeletionRecoveryException('Source deletion cannot be force-requeued while its cleanup job is running.');
        }

        $sourceDeletionInitiationResult = $this->sourceApiFacade->scheduleSourceDeletionRecovery(
            $sourceDto->organizationId,
            $sourceDto->id,
            true,
            self::RECOVERY_ORIGIN,
        );
        if (null === $sourceDeletionInitiationResult?->jobId || null === $sourceDeletionInitiationResult->deletionRunId) {
            throw new SourceDeletionRecoveryException('Source deletion cannot be force-requeued while its cleanup execution lease is held.');
        }

        $jobDto = $this->jobApiFacade->findById($sourceDeletionInitiationResult->jobId);
        if (! $jobDto instanceof JobDto) {
            throw new SourceDeletionRecoveryException('Source deletion force-recovery job was not found after scheduling.');
        }

        $idempotencyKey = $jobDto->idempotencyKey ?? '';
        $recoveryRunId  = $sourceDeletionInitiationResult->deletionRunId;

        $this->recordAudit(
            'source.delete.force_requeued_by_admin',
            $sourceDeletionRecoverySummary,
            $adminUserId,
            [
                'new_job_id'      => $jobDto->id,
                'job_created'     => true,
                'idempotency_key' => $idempotencyKey,
                'deletion_run_id' => $recoveryRunId,
                'action_origin'   => self::RECOVERY_ORIGIN,
            ],
        );

        return 'Source deletion force requeue was scheduled.';
    }

    public function cancelDeletion(
        string $sourceId,
        string $adminUserId,
        string $confirmation,
        bool $cancelJobs,
    ): string {
        if ('CANCEL DELETE' !== trim($confirmation)) {
            throw new SourceDeletionRecoveryException('Type CANCEL DELETE to confirm cancellation.');
        }

        $sourceDto                     = $this->requireDeletingSource($sourceId);
        $sourceDeletionRecoverySummary = $this->summaryForSource($sourceDto);
        $jobs                          = $this->jobApiFacade->findSourceDeleteJobs($sourceDto->organizationId, $sourceDto->id);

        if ($this->activeJobCount($jobs) > 0) {
            if (! $cancelJobs) {
                throw new SourceDeletionRecoveryException('Source deletion has active delete jobs.');
            }

            foreach ($jobs as $job) {
                if (in_array($job->status, ['held', 'audited', 'pending'], true)) {
                    $this->jobApiFacade->cancel($job->id, $sourceDto->organizationId);
                }
            }

            $jobs = $this->jobApiFacade->findSourceDeleteJobs($sourceDto->organizationId, $sourceDto->id);
            if ($this->activeJobCount($jobs) > 0) {
                throw new SourceDeletionRecoveryException('Source deletion still has active delete jobs.');
            }
        }

        if ($this->hasStartedDeleteJob($jobs)) {
            throw new SourceDeletionRecoveryException('Cancel deletion is blocked because a retained delete job may have already started cleanup.');
        }

        if (! $this->sourceApiFacade->cancelDeletingAsInactive($sourceDto->organizationId, $sourceDto->id) instanceof SourceDto) {
            throw new SourceDeletionRecoveryException('Source deletion could not be canceled.');
        }

        $this->recordAudit(
            'source.delete.canceled_by_admin',
            $sourceDeletionRecoverySummary,
            $adminUserId,
            [
                'cancel_jobs_requested' => $cancelJobs,
                'restored_status'       => 'active',
                'restored_active'       => false,
                'action_origin'         => self::RECOVERY_ORIGIN,
            ],
        );

        return 'Source deletion was canceled and the source was restored inactive.';
    }

    public function forceFinalizeDeletion(
        string $sourceId,
        string $adminUserId,
        string $confirmation,
        bool $acknowledgeOrphanedArchives,
    ): string {
        if ('FORCE DELETE SOURCE' !== trim($confirmation)) {
            throw new SourceDeletionRecoveryException('Type FORCE DELETE SOURCE to confirm force finalize.');
        }

        $sourceDto                     = $this->requireDeletingSource($sourceId);
        $packages                      = $this->packagesForSource($sourceDto);
        $sourceDeletionRecoverySummary = $this->summaryForSourcePackages($sourceDto, $packages);
        if ($sourceDeletionRecoverySummary->activeJobsCount > 0) {
            throw new SourceDeletionRecoveryException('Source deletion has active delete jobs.');
        }

        $deletionRunId        = $this->recoveryOperationId('source-delete-force-finalize', $sourceDto->id);
        $packagesDeleted      = 0;
        $releasesDeleted      = 0;
        $archiveScheduled     = 0;
        $archiveFailed        = 0;
        $archiveWarnings      = [];

        /** @var null|int $webhookEventsDeleted */
        $webhookEventsDeleted = null;

        // By reference: the counters change while the loop runs. An audit failure is logged and never replaces the original exception.
        $recordInterrupted = function() use (&$webhookEventsDeleted, &$packagesDeleted, &$releasesDeleted, &$archiveScheduled, &$archiveFailed, &$archiveWarnings, $sourceDeletionRecoverySummary, $sourceDto, $adminUserId, $deletionRunId, $packages, $acknowledgeOrphanedArchives): void {
            try {
                $this->recordAudit(
                    'source.delete.force_finalize_interrupted_by_admin',
                    $sourceDeletionRecoverySummary,
                    $adminUserId,
                    [
                        'severity'                      => 'high',
                        'deletion_run_id'               => $deletionRunId,
                        'packages_found'                => count($packages),
                        'packages_deleted'              => $packagesDeleted,
                        'releases_deleted'              => $releasesDeleted,
                        'archive_cleanup_scheduled'     => $archiveScheduled,
                        'archive_cleanup_failed'        => $archiveFailed,
                        'archive_cleanup_warnings'      => $archiveWarnings,
                        'acknowledge_orphaned_archives' => $acknowledgeOrphanedArchives,
                        'action_origin'                 => self::RECOVERY_ORIGIN,
                    ] + (null !== $webhookEventsDeleted ? [
                        'webhook_events_deleted' => $webhookEventsDeleted,
                    ] : []),
                );
            } catch (Throwable $auditThrowable) {
                $this->logger->error('Source force-finalize interrupted audit failed.', SafeExceptionContext::exception($auditThrowable) + [
                    'source_id' => $sourceDto->id,
                ]);
            }
        };

        try {
            foreach ($packages as $package) {
                try {
                    $cleanup = $this->archiveStorageCleanupApiFacade->schedulePackageArchiveCleanup(
                        $sourceDto->organizationId,
                        $package->name,
                        $deletionRunId,
                        self::RECOVERY_ORIGIN,
                    );
                    $archiveScheduled += $cleanup['scheduled'];
                    $archiveFailed += $cleanup['failed'];
                    if ($cleanup['failed'] > 0) {
                        $archiveWarnings[] = [
                            'package_name' => $package->name,
                            'error'        => 'Archive cleanup could not be fully scheduled.',
                        ];
                    }
                } catch (Throwable $throwable) {
                    ++$archiveFailed;
                    $archiveWarnings[] = [
                        'package_name' => $package->name,
                        'error'        => 'Archive cleanup could not be scheduled.',
                    ];
                    $this->logger->error('Source force-finalize archive cleanup scheduling failed.', SafeExceptionContext::exception($throwable) + [
                        'package_name' => $package->name,
                        'source_id'    => $sourceDto->id,
                    ]);
                }

                if ($archiveFailed > 0 && ! $acknowledgeOrphanedArchives) {
                    throw new SourceDeletionRecoveryException('Archive cleanup could not be fully scheduled. Acknowledge orphaned archives to continue.');
                }

                $releasesDeleted += $this->releaseApiFacade->deleteReleasesForPackage(
                    $package->name,
                    $sourceDto->organizationId,
                );

                if ($this->packageApiFacade->deletePackage($sourceDto->organizationId, $package->name)) {
                    ++$packagesDeleted;
                }
            }
        } catch (Throwable $throwable) {
            // Something was already deleted or scheduled: leave an audit trail of the partial run.
            if ($packagesDeleted + $releasesDeleted + $archiveScheduled > 0) {
                $recordInterrupted();
            }

            throw $throwable;
        }

        try {
            $webhookEventsDeleted = $this->webhookApiFacade->deleteEventsForSource($sourceDto->id);
            if (! $this->sourceApiFacade->deleteSource($sourceDto->id)) {
                throw new SourceDeletionRecoveryException('Source row could not be deleted.');
            }
        } catch (Throwable $throwable) {
            $recordInterrupted();

            throw $throwable;
        }

        $context = [
            'severity'                      => 'high',
            'deletion_run_id'               => $deletionRunId,
            'packages_found'                => count($packages),
            'packages_deleted'              => $packagesDeleted,
            'releases_deleted'              => $releasesDeleted,
            'archive_cleanup_scheduled'     => $archiveScheduled,
            'archive_cleanup_failed'        => $archiveFailed,
            'archive_cleanup_warnings'      => $archiveWarnings,
            'acknowledge_orphaned_archives' => $acknowledgeOrphanedArchives,
            'webhook_events_deleted'        => $webhookEventsDeleted,
            'action_origin'                 => self::RECOVERY_ORIGIN,
        ];

        $this->recordAudit(
            'source.delete.force_finalized_by_admin',
            $sourceDeletionRecoverySummary,
            $adminUserId,
            $context,
        );

        return 'Source deletion was force finalized.';
    }

    /**
     * @param list<PackageDto> $packages
     */
    private function summaryForSourcePackages(SourceDto $sourceDto, array $packages): SourceDeletionRecoverySummary
    {
        return $this->buildSummary(
            $sourceDto,
            $this->jobApiFacade->findSourceDeleteJobs($sourceDto->organizationId, $sourceDto->id),
            count($packages),
            $this->releaseCount($packages),
        );
    }

    private function requireDeletingSource(string $sourceId): SourceDto
    {
        $source = $this->sourceApiFacade->findSource($sourceId);
        if (! $source instanceof SourceDto) {
            throw new SourceDeletionRecoveryException('Source not found.');
        }

        if ('deleting' !== $source->status) {
            throw new SourceDeletionRecoveryException('Source is not deleting.');
        }

        return $source;
    }

    /**
     * @param list<JobDto> $jobs
     */
    private function buildSummary(SourceDto $sourceDto, array $jobs, int $packageCount, int $releaseCount): SourceDeletionRecoverySummary
    {
        $latestJob      = $jobs[0] ?? null;
        $activeJobs     = $this->activeJobCount($jobs);
        $relatedJobRows = [];
        foreach ($jobs as $job) {
            $relatedJobRows[] = $this->jobToArray($job);
        }

        return new SourceDeletionRecoverySummary(
            sourceId: $sourceDto->id,
            organizationId: $sourceDto->organizationId,
            health: $this->health($jobs),
            relatedJobsCount: count($jobs),
            activeJobsCount: $activeJobs,
            latestJobId: $latestJob?->id,
            latestJobStatus: $latestJob?->status,
            latestJobCreatedAt: $latestJob?->createdAt,
            latestJobError: $latestJob?->errorMessage,
            linkedPackagesCount: $packageCount,
            linkedReleasesCount: $releaseCount,
            webhookExternalIdPresent: '' !== trim((string) ($sourceDto->providerConfig['webhook_external_id'] ?? '')),
            canRequeue: 0 === $activeJobs,
            canForceRequeue: ! array_any($jobs, static fn (JobDto $jobDto): bool => 'running' === $jobDto->status),
            canCancel: 0 === $activeJobs && ! $this->hasStartedDeleteJob($jobs),
            canForceFinalize: 0 === $activeJobs,
            relatedJobs: $relatedJobRows,
        );
    }

    /**
     * @param list<JobDto> $jobs
     */
    private function health(array $jobs): string
    {
        if ($this->activeJobCount($jobs) > 0) {
            return SourceDeletionRecoverySummary::HEALTH_IN_PROGRESS;
        }

        foreach ($jobs as $job) {
            if ('failed' === $job->status) {
                return SourceDeletionRecoverySummary::HEALTH_FAILED;
            }
        }

        if ([] === $jobs) {
            return SourceDeletionRecoverySummary::HEALTH_STALE_WITHOUT_JOB;
        }

        if ('completed' === $jobs[0]->status) {
            return SourceDeletionRecoverySummary::HEALTH_COMPLETED_BUT_SOURCE_EXISTS;
        }

        return SourceDeletionRecoverySummary::HEALTH_UNKNOWN;
    }

    /**
     * @param list<JobDto> $jobs
     */
    private function activeJobCount(array $jobs): int
    {
        return count(array_filter(
            $jobs,
            static fn (JobDto $jobDto): bool => in_array($jobDto->status, ['held', 'audited', 'pending', 'running'], true),
        ));
    }

    /**
     * A job "may have started cleanup" once its first attempt was claimed.
     * Retry scheduling clears `startedAt`, so `attemptCount` is the durable
     * evidence that external idempotent effects may already have begun. A
     * canceled job that was never claimed keeps attemptCount=0 and does not
     * block a later cancellation and re-initiation.
     *
     * @param list<JobDto> $jobs
     */
    private function hasStartedDeleteJob(array $jobs): bool
    {
        return array_any($jobs, fn (JobDto $jobDto): bool => 0 < $jobDto->attemptCount
            || null !== $jobDto->startedAt
            || in_array($jobDto->status, ['running', 'completed', 'failed'], true));
    }

    /**
     * @param array<string, SourceDto> $sourcesById
     *
     * @return array<string, list<PackageDto>>
     */
    private function packagesBySource(array $sourcesById): array
    {
        $organizationIds = [];
        foreach ($sourcesById as $source) {
            $organizationIds[$source->organizationId] = true;
        }

        $packagesBySource = [];
        foreach (array_keys($organizationIds) as $organizationId) {
            foreach ($this->packageApiFacade->packagesForOrganization((string) $organizationId) as $packageDto) {
                $source = $sourcesById[$packageDto->sourceId] ?? null;
                if (null === $source || $packageDto->organizationId !== $source->organizationId) {
                    continue;
                }

                $packagesBySource[$packageDto->sourceId][] = $packageDto;
            }
        }

        return $packagesBySource;
    }

    /**
     * @return list<PackageDto>
     */
    private function packagesForSource(SourceDto $sourceDto): array
    {
        return array_values(array_filter(
            $this->packageApiFacade->packagesForOrganization($sourceDto->organizationId),
            static fn (PackageDto $packageDto): bool => $packageDto->organizationId === $sourceDto->organizationId
                && $packageDto->sourceId === $sourceDto->id,
        ));
    }

    /**
     * @param list<PackageDto> $packages
     */
    private function releaseCount(array $packages): int
    {
        $count = 0;
        foreach ($packages as $package) {
            $count += count($this->releaseApiFacade->releasesForPackage($package->name, true, $package->organizationId));
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function jobToArray(JobDto $jobDto): array
    {
        return [
            'id'              => $jobDto->id,
            'status'          => $jobDto->status,
            'created_at'      => $jobDto->createdAt,
            'started_at'      => $jobDto->startedAt,
            'completed_at'    => $jobDto->completedAt,
            'error_message'   => $jobDto->errorMessage,
            'idempotency_key' => $jobDto->idempotencyKey,
            'deletion_run_id' => is_scalar($jobDto->payload['deletion_run_id'] ?? null)
                ? (string) $jobDto->payload['deletion_run_id']
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function recordAudit(
        string $event,
        SourceDeletionRecoverySummary $sourceDeletionRecoverySummary,
        string $adminUserId,
        array $context,
    ): void {
        $this->auditApiFacade->record(
            $event,
            [
                'admin_user_id'            => $adminUserId,
                'source_id'                => $sourceDeletionRecoverySummary->sourceId,
                'organization_id'          => $sourceDeletionRecoverySummary->organizationId,
                'previous_recovery_health' => $sourceDeletionRecoverySummary->health,
                'related_jobs_count'       => $sourceDeletionRecoverySummary->relatedJobsCount,
                'active_jobs_count'        => $sourceDeletionRecoverySummary->activeJobsCount,
                'linked_packages_count'    => $sourceDeletionRecoverySummary->linkedPackagesCount,
                'linked_releases_count'    => $sourceDeletionRecoverySummary->linkedReleasesCount,
                ...$context,
            ],
            $sourceDeletionRecoverySummary->organizationId,
            $sourceDeletionRecoverySummary->sourceId,
            actorId: $adminUserId,
        );
    }

    /**
     * @param list<array<string, mixed>> $relatedJobs
     */
    private function activeDeleteJob(array $relatedJobs): ?JobDto
    {
        foreach ($relatedJobs as $relatedJob) {
            if (
                in_array($relatedJob['status'] ?? null, ['held', 'audited', 'pending', 'running'], true)
                && is_scalar($relatedJob['id'] ?? null)
            ) {
                return $this->jobApiFacade->findById((string) $relatedJob['id']);
            }
        }

        return null;
    }

    private function recoveryOperationId(string $prefix, string $sourceId): string
    {
        return sprintf('%s:%s:%s', $prefix, $sourceId, bin2hex(random_bytes(16)));
    }
}
