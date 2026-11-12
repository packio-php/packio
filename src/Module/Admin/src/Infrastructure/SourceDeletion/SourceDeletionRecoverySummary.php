<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\SourceDeletion;

final readonly class SourceDeletionRecoverySummary
{
    public const string HEALTH_IN_PROGRESS                 = 'in_progress';
    public const string HEALTH_FAILED                      = 'failed';
    public const string HEALTH_STALE_WITHOUT_JOB           = 'stale_without_job';
    public const string HEALTH_COMPLETED_BUT_SOURCE_EXISTS = 'completed_but_source_exists';
    public const string HEALTH_UNKNOWN                     = 'unknown';

    /**
     * @param list<array<string, mixed>> $relatedJobs
     */
    public function __construct(
        public string $sourceId,
        public string $organizationId,
        public string $health,
        public int $relatedJobsCount,
        public int $activeJobsCount,
        public ?string $latestJobId,
        public ?string $latestJobStatus,
        public ?string $latestJobCreatedAt,
        public ?string $latestJobError,
        public int $linkedPackagesCount,
        public int $linkedReleasesCount,
        public bool $webhookExternalIdPresent,
        public bool $canRequeue,
        public bool $canForceRequeue,
        public bool $canCancel,
        public bool $canForceFinalize,
        public array $relatedJobs = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_id'                   => $this->sourceId,
            'organization_id'             => $this->organizationId,
            'health'                      => $this->health,
            'related_jobs_count'          => $this->relatedJobsCount,
            'active_jobs_count'           => $this->activeJobsCount,
            'latest_job_id'               => $this->latestJobId,
            'latest_job_status'           => $this->latestJobStatus,
            'latest_job_created_at'       => $this->latestJobCreatedAt,
            'latest_job_error'            => $this->latestJobError,
            'linked_packages_count'       => $this->linkedPackagesCount,
            'linked_releases_count'       => $this->linkedReleasesCount,
            'webhook_external_id_present' => $this->webhookExternalIdPresent,
            'can_requeue'                 => $this->canRequeue,
            'can_force_requeue'           => $this->canForceRequeue,
            'can_cancel'                  => $this->canCancel,
            'can_force_finalize'          => $this->canForceFinalize,
            'related_jobs'                => $this->relatedJobs,
        ];
    }
}
