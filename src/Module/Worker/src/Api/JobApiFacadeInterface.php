<?php

declare(strict_types=1);

namespace Packio\Worker\Api;

use Cake\Chronos\Chronos;
use Packio\Shared\Api\Dto\PaginatedResultDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Api\Dto\JobLogDto;

interface JobApiFacadeInterface
{
    /**
     * @param list<string> $types
     * @param list<string> $statuses
     *
     * @return list<JobDto>
     */
    public function findByOrganizationDeletionRun(
        string $organizationId,
        string $deletionRunId,
        array $types = [],
        array $statuses = [],
    ): array;

    public function findById(string $id): ?JobDto;

    /**
     * @return list<JobDto>
     */
    public function findSourceDeleteJobs(string $organizationId, string $sourceId): array;

    /**
     * @return list<JobLogDto>
     */
    public function findLogsByJob(string $jobId): array;

    public function cancel(string $jobId, string $organizationId): bool;

    /**
     * Find jobs across all organizations with pagination.
     *
     * @phpstan-return PaginatedResultDto<JobDto>
     */
    public function findPaginatedAll(PaginationCriteria $paginationCriteria, JobFilterCriteria $jobFilterCriteria): PaginatedResultDto;

    /**
     * @return list<string>
     */
    public function jobTypes(?string $organizationId = null): array;

    /**
     * @return array<string, int>
     */
    public function countAllByStatus(JobFilterCriteria $jobFilterCriteria): array;

    /**
     * @return array{pending: int, running: int, failed: int, retrying: int, stuck: int, oldest_ready_age_seconds: ?int}
     */
    public function queueProgress(?string $organizationId = null): array;

    public function restartAsAdmin(string $jobId, string $adminUserId): ?JobDto;

    /**
     * Enqueue a new job for async processing.
     *
     * @param array<string, mixed> $payload
     */
    public function enqueue(
        string $organizationId,
        string $type,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): JobDto;

    /**
     * Enqueue a job only once for the given organization/type/idempotency key.
     *
     * @param array<string, mixed> $payload
     */
    public function enqueueOnce(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): JobDto;

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueueOnceHeld(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): JobDto;

    public function publish(string $jobId, string $organizationId): bool;

    public function confirmAudit(string $jobId, string $organizationId): bool;

    public function publishAfterAudit(string $jobId, string $organizationId): bool;

    /**
     * @param array<string, mixed> $payload
     *
     * @return bool true when a new source.create job was created
     */
    public function enqueueSourceCreation(string $organizationId, string $idempotencyKey, array $payload): bool;

    /**
     * Coalesce source.sync jobs by reusing a pending one for the same source.
     *
     * @param array<string, mixed> $payload
     */
    public function enqueueSourceSyncOnce(
        string $organizationId,
        string $sourceId,
        string $idempotencyKey,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): JobDto;
}
