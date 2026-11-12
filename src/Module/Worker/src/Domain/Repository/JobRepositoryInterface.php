<?php

declare(strict_types=1);

namespace Packio\Worker\Domain\Repository;

use Cake\Chronos\Chronos;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Domain\Entity\Job;

interface JobRepositoryInterface
{
    public function save(Job $job): void;

    public function findById(string $id): ?Job;

    public function findByIdempotencyKey(string $organizationId, string $type, string $idempotencyKey): ?Job;

    /** Releases a terminal job's key without deleting its history or changing active jobs. */
    public function releaseTerminalIdempotencyKey(string $organizationId, string $type, string $idempotencyKey): void;

    /** Advances one lifecycle job only from its expected current status. */
    public function advanceLifecycle(string $id, string $organizationId, string $fromStatus, string $toStatus): bool;

    /**
     * @return list<Job>
     */
    public function findSourceDeleteJobs(string $organizationId, string $sourceId): array;

    /**
     * Delete jobs older than the specified date.
     *
     * @return int Number of deleted jobs
     */
    public function deleteOlderThan(Chronos $threshold): int;

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<Job>
     */
    public function findByOrganization(string $organizationId, array $filters = []): array;

    /**
     * Claims the next available job for processing.
     */
    public function dequeue(string $workerId, int $timeoutSeconds = 30): ?Job;

    /**
     * Finds "stuck" jobs (running longer than timeout).
     *
     * @return list<Job>
     */
    public function findStuck(int $timeoutMinutes = 30): array;

    /**
     * Find jobs across all organizations with pagination.
     *
     * @phpstan-return array{items: list<Job>, total: int, currentPage: int, perPage: int}
     */
    public function findPaginatedAll(PaginationCriteria $paginationCriteria, JobFilterCriteria $jobFilterCriteria): array;

    /**
     * @return list<string>
     */
    public function distinctTypes(?string $organizationId = null): array;

    /**
     * @return array<string, int>
     */
    public function countAllByStatus(JobFilterCriteria $jobFilterCriteria): array;

    /**
     * @return array{pending: int, running: int, failed: int, retrying: int, stuck: int, oldest_ready_age_seconds: ?int}
     */
    public function queueProgress(?string $organizationId = null): array;
}
