<?php

declare(strict_types=1);

namespace Packio\Worker\Infrastructure\Facade;

use Cake\Chronos\Chronos;
use Packio\Shared\Api\Dto\PaginatedResultDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Api\Dto\JobLogDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use Packio\Worker\Application\JobQueueService;
use Packio\Worker\Application\JobRestartService;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use Packio\Worker\Infrastructure\Mapper\JobDtoMapper;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function array_map;

final readonly class JobApiFacade implements JobApiFacadeInterface
{
    public function __construct(
        private JobRepositoryInterface $jobRepository,
        private JobLogRepositoryInterface $jobLogRepository,
        private JobQueueService $jobQueueService,
        private JobRestartService $jobRestartService,
        private JobDtoMapper $jobDtoMapper,
        private ObjectMapperInterface $objectMapper,
    ) {}

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
    ): array {
        $filters = [
            'deletion_run_id' => $deletionRunId,
            'limit'           => null,
        ];

        if ([] !== $types) {
            $filters['type'] = $types;
        }

        if ([] !== $statuses) {
            $filters['status'] = $statuses;
        }

        $jobs = $this->jobRepository->findByOrganization($organizationId, $filters);

        return $this->jobDtoMapper->mapList($jobs);
    }

    public function findById(string $id): ?JobDto
    {
        $job = $this->jobRepository->findById($id);

        if (! $job instanceof Job) {
            return null;
        }

        $warningCounts = $this->jobLogRepository->countWarningsByJobIds([$id]);

        return $this->jobDtoMapper->map($job, $warningCounts[$id] ?? 0);
    }

    /**
     * @return list<JobDto>
     */
    public function findSourceDeleteJobs(string $organizationId, string $sourceId): array
    {
        return $this->jobDtoMapper->mapList(
            $this->jobRepository->findSourceDeleteJobs($organizationId, $sourceId),
        );
    }

    /**
     * @return list<JobLogDto>
     */
    public function findLogsByJob(string $jobId): array
    {
        $logs = $this->jobLogRepository->findByJob($jobId);

        return array_map(
            fn (JobLog $jobLog): JobLogDto => $this->objectMapper->map($jobLog, JobLogDto::class),
            $logs,
        );
    }

    public function cancel(string $jobId, string $organizationId): bool
    {
        $job = $this->jobRepository->findById($jobId);

        if (! $job instanceof Job || $job->organizationId() !== $organizationId) {
            return false;
        }

        return $this->jobQueueService->cancel($jobId);
    }

    /**
     * @phpstan-return PaginatedResultDto<JobDto>
     */
    public function findPaginatedAll(PaginationCriteria $paginationCriteria, JobFilterCriteria $jobFilterCriteria): PaginatedResultDto
    {
        $result = $this->jobRepository->findPaginatedAll($paginationCriteria, $jobFilterCriteria);

        /** @var list<Job> $jobs */
        $jobs          = $result['items'];
        $warningCounts = $this->jobLogRepository->countWarningsByJobIds(
            array_map(
                static fn (Job $job): string => $job->getIdentifier()->toString(),
                $jobs,
            ),
        );
        $jobDtos = $this->jobDtoMapper->mapListWithWarningCounts($jobs, $warningCounts);

        return new PaginatedResultDto(
            items: $jobDtos,
            total: $result['total'],
            currentPage: $result['currentPage'],
            perPage: $result['perPage'],
        );
    }

    public function jobTypes(?string $organizationId = null): array
    {
        return $this->jobRepository->distinctTypes($organizationId);
    }

    public function countAllByStatus(JobFilterCriteria $jobFilterCriteria): array
    {
        return $this->jobRepository->countAllByStatus($jobFilterCriteria);
    }

    public function queueProgress(?string $organizationId = null): array
    {
        return $this->jobRepository->queueProgress($organizationId);
    }

    public function restartAsAdmin(string $jobId, string $adminUserId): ?JobDto
    {
        $job = $this->jobRestartService->restartAsAdmin($jobId, $adminUserId);

        return $job instanceof Job ? $this->jobDtoMapper->map($job) : null;
    }

    public function enqueue(
        string $organizationId,
        string $type,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): JobDto {
        $job = $this->jobQueueService->enqueue($organizationId, $type, $payload, $priority, $scheduledAt, $maxAttempts);

        return $this->jobDtoMapper->map($job);
    }

    public function enqueueOnce(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): JobDto {
        $job = $this->jobQueueService->enqueueOnce(
            $organizationId,
            $type,
            $idempotencyKey,
            $payload,
            $priority,
            $scheduledAt,
            $maxAttempts,
        );

        return $this->jobDtoMapper->map($job);
    }

    public function enqueueOnceHeld(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): JobDto {
        return $this->jobDtoMapper->map($this->jobQueueService->enqueueOnceHeld(
            $organizationId,
            $type,
            $idempotencyKey,
            $payload,
            $priority,
            $scheduledAt,
            $maxAttempts,
        ));
    }

    public function publish(string $jobId, string $organizationId): bool
    {
        $job = $this->jobRepository->findById($jobId);

        return $job instanceof Job
            && $job->organizationId() === $organizationId
            && $this->jobQueueService->publish($jobId, $organizationId);
    }

    public function confirmAudit(string $jobId, string $organizationId): bool
    {
        return $this->jobQueueService->confirmAudit($jobId, $organizationId);
    }

    public function publishAfterAudit(string $jobId, string $organizationId): bool
    {
        return $this->jobQueueService->publishAfterAudit($jobId, $organizationId);
    }

    public function enqueueSourceCreation(string $organizationId, string $idempotencyKey, array $payload): bool
    {
        return $this->jobQueueService->enqueueSourceCreation($organizationId, $idempotencyKey, $payload)->created;
    }

    public function enqueueSourceSyncOnce(
        string $organizationId,
        string $sourceId,
        string $idempotencyKey,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): JobDto {
        return $this->jobDtoMapper->map($this->jobQueueService->enqueueSourceSyncOnce(
            $organizationId,
            $sourceId,
            $idempotencyKey,
            $payload,
            $priority,
            $scheduledAt,
            $maxAttempts,
        ));
    }
}
