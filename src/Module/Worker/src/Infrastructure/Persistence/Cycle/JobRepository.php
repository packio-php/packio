<?php

declare(strict_types=1);

namespace Packio\Worker\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Injection\Fragment;
use Cycle\Database\Query\SelectQuery;
use Cycle\ORM\Entity\Behavior\Exception\OptimisticLock\OptimisticLockException;
use Cycle\ORM\Select;
use Cycle\ORM\Select\QueryBuilder;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function array_fill_keys;
use function array_key_exists;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_scalar;
use function iterator_to_array;
use function sleep;
use function sort;
use function str_replace;
use function time;
use function trim;
use function usort;

/**
 * @extends AbstractWriteRepository<Job>
 */
final class JobRepository extends AbstractWriteRepository implements JobRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string TABLE_NAME         = 'jobs';
    private const string ENTITY_CLASS       = Job::class;
    private const int STUCK_TIMEOUT_MINUTES = 30;

    /** @var list<string> */
    private const array ALLOWED_GLOBAL_SORT_FIELDS = [
        'createdAt',
        'scheduledAt',
        'startedAt',
        'completedAt',
        'priority',
        'attemptCount',
        'maxAttempts',
        'type',
        'status',
        'organizationId',
    ];

    /**
     * @throws RepositoryPersistException
     */
    public function save(Job $job): void
    {
        $this->persistAndFlush($job);
    }

    public function findById(string $id): ?Job
    {
        $entity = $this->select()->where([
            'uuid' => $id,
        ])->fetchOne();

        return $entity instanceof Job ? $entity : null;
    }

    public function findByIdempotencyKey(string $organizationId, string $type, string $idempotencyKey): ?Job
    {
        $entity = $this->select()
            ->where([
                'organizationId' => $organizationId,
                'type'           => $type,
                'idempotencyKey' => $idempotencyKey,
            ])
            ->fetchOne()
        ;

        return $entity instanceof Job ? $entity : null;
    }

    public function releaseTerminalIdempotencyKey(string $organizationId, string $type, string $idempotencyKey): void
    {
        $job = $this->findByIdempotencyKey($organizationId, $type, $idempotencyKey);
        if (! $job instanceof Job) {
            return;
        }

        // Match the exact row and terminal status: another producer may already
        // have released this key and scheduled a new active job with the same key.
        $changed = $this->database()->update(self::TABLE_NAME)->values([
            'idempotency_key' => null,
            'version'         => new Fragment('version + 1'),
        ])->where('uuid', $job->getIdentifier()->toString())
            ->where('organization_id', $organizationId)
            ->where('type', $type)
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', 'in', [Job::STATUS_COMPLETED, Job::STATUS_FAILED, Job::STATUS_CANCELED])
            ->run()
        ;

        if (1 === $changed) {
            $this->orm->getHeap()->detach($job);
        }
    }

    public function advanceLifecycle(string $id, string $organizationId, string $fromStatus, string $toStatus): bool
    {
        $changed = $this->database()->update(self::TABLE_NAME)->values([
            'status'  => $toStatus,
            'version' => new Fragment('version + 1'),
        ])->where('uuid', $id)
            ->where('organization_id', $organizationId)
            ->where('status', $fromStatus)
            ->run()
        ;

        $job = $this->findById($id);
        if ($job instanceof Job) {
            $this->orm->getHeap()->detach($job);
        }

        return 1 === $changed;
    }

    public function findSourceDeleteJobs(string $organizationId, string $sourceId): array
    {
        $jobsById = [];

        foreach ($this->select()
            ->where([
                'organizationId' => $organizationId,
                'type'           => 'source.delete',
            ])
            ->whereJson('payload->source_id', $sourceId)
            ->orderBy([
                'createdAt' => 'DESC',
            ])
            ->fetchAll() as $job) {
            $jobsById[$job->getIdentifier()->toString()] = $job;
        }

        $idempotencyJob = $this->findByIdempotencyKey($organizationId, 'source.delete', 'source-delete:' . $sourceId);
        if ($idempotencyJob instanceof Job) {
            $jobsById[$idempotencyJob->getIdentifier()->toString()] = $idempotencyJob;
        }

        $jobs = array_values($jobsById);
        usort(
            $jobs,
            static fn (Job $left, Job $right): int => $right->getCreatedAt() <=> $left->getCreatedAt(),
        );

        return $jobs;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<Job>
     */
    public function findByOrganization(string $organizationId, array $filters = []): array
    {
        $select = $this->select()->where([
            'organizationId' => $organizationId,
        ]);

        if (array_key_exists('status', $filters)) {
            $statuses = $this->stringFilterValues($filters['status']);
            if ([] === $statuses) {
                return [];
            }

            if (1 === count($statuses)) {
                $select->where([
                    'status' => $statuses[0],
                ]);
            } else {
                $select->where([
                    'status' => [
                        'in' => $statuses,
                    ],
                ]);
            }
        }

        if (array_key_exists('type', $filters)) {
            $types = $this->stringFilterValues($filters['type']);
            if ([] === $types) {
                return [];
            }

            if (1 === count($types)) {
                $select->where([
                    'type' => $types[0],
                ]);
            } else {
                $select->where([
                    'type' => [
                        'in' => $types,
                    ],
                ]);
            }
        }

        if (array_key_exists('idempotency_key', $filters)) {
            $idempotencyKey = $this->stringFilterValue($filters['idempotency_key']);
            if (null === $idempotencyKey) {
                return [];
            }

            $select->where([
                'idempotencyKey' => $idempotencyKey,
            ]);
        }

        if (array_key_exists('deletion_run_id', $filters)) {
            $deletionRunId = $this->stringFilterValue($filters['deletion_run_id']);
            if (null === $deletionRunId) {
                return [];
            }

            $select->whereJson('payload->deletion_run_id', $deletionRunId);
        }

        if (array_key_exists('source_id', $filters)) {
            $sourceId = $this->stringFilterValue($filters['source_id']);
            if (null === $sourceId) {
                return [];
            }

            $select->whereJson('payload->source_id', $sourceId);
        }

        $limit = $this->organizationFilterLimit($filters);
        $select->orderBy([
            'createdAt' => 'DESC',
        ]);

        if (null !== $limit) {
            $select->limit($limit);
        }

        return iterator_to_array(
            $select->fetchAll(),
            false,
        );
    }

    /**
     * @throws RepositoryPersistException
     */
    public function dequeue(string $workerId, int $timeoutSeconds = 30): ?Job
    {
        $deadline = time() + $timeoutSeconds;

        while (time() < $deadline) {
            $job = $this->select()
                ->where([
                    'status' => Job::STATUS_PENDING,
                ])
                ->where('scheduledAt', '<=', new Chronos())
                ->orderBy([
                    'priority'    => 'DESC',
                    'scheduledAt' => 'ASC',
                ])
                ->limit(1)
                ->fetchOne()
            ;

            if ($job instanceof Job) {
                $job->markRunning($workerId);

                try {
                    $this->persistAndFlush($job);

                    return $job;
                } catch (RepositoryPersistException $e) {
                    if ($e->getPrevious() instanceof OptimisticLockException) {
                        // Drop the stale copy, or the next select returns it again and never wins.
                        $this->orm->getHeap()->detach($job);

                        continue;
                    }

                    throw $e;
                }
            }

            sleep(1);
        }

        return null;
    }

    public function findStuck(int $timeoutMinutes = self::STUCK_TIMEOUT_MINUTES): array
    {
        $threshold = new Chronos("-{$timeoutMinutes} minutes");

        return iterator_to_array(
            $this->select()
                ->where([
                    'status' => Job::STATUS_RUNNING,
                ])
                ->where('startedAt', '<', $threshold)
                ->fetchAll(),
            false,
        );
    }

    public function queueProgress(?string $organizationId = null): array
    {
        $stuckTimeoutMinutes = self::STUCK_TIMEOUT_MINUTES;
        $query               = <<<SQL
            SELECT
                COUNT(*) FILTER (WHERE status = 'pending') AS pending,
                COUNT(*) FILTER (WHERE status = 'running') AS running,
                COUNT(*) FILTER (WHERE status = 'failed') AS failed,
                COUNT(*) FILTER (WHERE status = 'pending' AND attempt_count > 0) AS retrying,
                COUNT(*) FILTER (
                    WHERE status = 'running'
                    AND started_at < CURRENT_TIMESTAMP - INTERVAL '{$stuckTimeoutMinutes} minutes'
                ) AS stuck,
                FLOOR(EXTRACT(EPOCH FROM (
                    CURRENT_TIMESTAMP - MIN(scheduled_at) FILTER (
                        WHERE status = 'pending' AND scheduled_at <= CURRENT_TIMESTAMP
                    )
                )))::integer AS oldest_ready_age_seconds
            FROM jobs
            SQL;
        $parameters = [];
        if (null !== $organizationId) {
            $query .= ' WHERE organization_id = ?';
            $parameters[] = $organizationId;
        }

        $rows = $this->database()->getDriver(DatabaseInterface::WRITE)
            ->query($query, $parameters)
            ->fetchAll()
        ;
        $row = $rows[0] ?? [];

        return [
            'pending'                  => (int) ($row['pending'] ?? 0),
            'running'                  => (int) ($row['running'] ?? 0),
            'failed'                   => (int) ($row['failed'] ?? 0),
            'retrying'                 => (int) ($row['retrying'] ?? 0),
            'stuck'                    => (int) ($row['stuck'] ?? 0),
            'oldest_ready_age_seconds' => null === ($row['oldest_ready_age_seconds'] ?? null)
                ? null
                : (int) $row['oldest_ready_age_seconds'],
        ];
    }

    public function deleteOlderThan(Chronos $threshold): int
    {
        return $this->database()->delete(self::TABLE_NAME)
            ->where('created_at', '<', $threshold)
            ->where('status', 'in', [Job::STATUS_COMPLETED, Job::STATUS_FAILED, Job::STATUS_CANCELED])
            // Keep source.delete jobs of a still-deleting source: Admin SourceDeletionRecoveryService reads them.
            ->where(new Fragment(<<<'SQL'
                NOT (type = 'source.delete' AND EXISTS (
                    SELECT 1 FROM sources s
                    WHERE s.organization_id = jobs.organization_id AND s.status = 'deleting'
                      AND CAST(s.uuid AS TEXT) IN (jobs.payload->>'source_id', substring(jobs.idempotency_key from '^source-delete:([^:]+)'))
                ))
                SQL))
            ->run()
        ;
    }

    public function findPaginatedAll(PaginationCriteria $paginationCriteria, JobFilterCriteria $jobFilterCriteria): array
    {
        $select = $this->filteredSelect($jobFilterCriteria);
        $select->orderBy([
            $this->globalSortField($paginationCriteria->sortBy) => $paginationCriteria->sortDirection->sql(),
            'uuid'                                              => 'DESC',
        ]);

        $total = $select->count();
        $items = iterator_to_array(
            $select
                ->offset($paginationCriteria->offset())
                ->limit($paginationCriteria->limit())
                ->fetchAll(),
            false,
        );

        return [
            'items'       => $items,
            'total'       => $total,
            'currentPage' => $paginationCriteria->page,
            'perPage'     => $paginationCriteria->perPage,
        ];
    }

    public function distinctTypes(?string $organizationId = null): array
    {
        $types       = [];
        $selectQuery = $this->database()
            ->select('type')
            ->distinct()
            ->from(self::TABLE_NAME)
        ;

        if (null !== $organizationId) {
            $selectQuery->where('organization_id', $organizationId);
        }

        foreach ($selectQuery->fetchAll() as $row) {
            if (isset($row['type']) && is_scalar($row['type'])) {
                $types[] = (string) $row['type'];
            }
        }

        $types = array_values(array_unique($types));
        sort($types);

        return $types;
    }

    public function countAllByStatus(JobFilterCriteria $jobFilterCriteria): array
    {
        $counts = array_fill_keys(JobFilterCriteria::ALLOWED_STATUSES, 0);

        $rows = $this->filteredRowsSelect($jobFilterCriteria, includeStatuses: false)
            ->columns([
                'status',
                new Fragment('COUNT(*) AS status_count'),
            ])
            ->groupBy('status')
            ->fetchAll()
        ;

        foreach ($rows as $row) {
            if (! isset($row['status'], $row['status_count'])) {
                continue;
            }

            if (! is_scalar($row['status'])) {
                continue;
            }
            $status = (string) $row['status'];
            if (in_array($status, JobFilterCriteria::ALLOWED_STATUSES, true)) {
                $counts[$status] = (int) $row['status_count'];
            }
        }

        return [
            Job::STATUS_PENDING   => $counts[Job::STATUS_PENDING],
            Job::STATUS_RUNNING   => $counts[Job::STATUS_RUNNING],
            Job::STATUS_COMPLETED => $counts[Job::STATUS_COMPLETED],
            Job::STATUS_FAILED    => $counts[Job::STATUS_FAILED],
            Job::STATUS_CANCELED  => $counts[Job::STATUS_CANCELED],
            Job::STATUS_HELD      => $counts[Job::STATUS_HELD],
            Job::STATUS_AUDITED   => $counts[Job::STATUS_AUDITED],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function organizationFilterLimit(array $filters): ?int
    {
        if (! array_key_exists('limit', $filters)) {
            return 50;
        }

        $limit = $filters['limit'];
        if (null === $limit) {
            return null;
        }

        if (! is_scalar($limit)) {
            return 50;
        }

        $resolvedLimit = (int) $limit;

        return $resolvedLimit > 0 ? $resolvedLimit : 50;
    }

    /**
     * @return list<string>
     */
    private function stringFilterValues(mixed $value): array
    {
        if (! is_array($value)) {
            $singleValue = $this->stringFilterValue($value);

            return null === $singleValue ? [] : [$singleValue];
        }

        $values = [];
        foreach ($value as $item) {
            $resolvedValue = $this->stringFilterValue($item);
            if (null !== $resolvedValue) {
                $values[] = $resolvedValue;
            }
        }

        return array_values(array_unique($values));
    }

    private function stringFilterValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $resolvedValue = trim((string) $value);

        return '' === $resolvedValue ? null : $resolvedValue;
    }

    /**
     * @return Select<Job>
     */
    private function filteredSelect(JobFilterCriteria $jobFilterCriteria, bool $includeStatuses = true): Select
    {
        $select = $this->select();

        if (null !== $jobFilterCriteria->organizationId) {
            $select->where([
                'organizationId' => $jobFilterCriteria->organizationId,
            ]);
        }

        if ($includeStatuses) {
            $this->applyStatusFilter($select, $jobFilterCriteria->statuses);
        }

        if (1 === count($jobFilterCriteria->types)) {
            $select->where([
                'type' => $jobFilterCriteria->types[0],
            ]);
        } elseif ([] !== $jobFilterCriteria->types) {
            $select->where([
                'type' => [
                    'in' => $jobFilterCriteria->types,
                ],
            ]);
        }

        if (null !== $jobFilterCriteria->search) {
            $search = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $jobFilterCriteria->search) . '%';
            $select->where(new Fragment(
                '(CAST(uuid AS TEXT) LIKE ? OR type LIKE ? OR idempotency_key LIKE ?)',
                $search,
                $search,
                $search,
            ));
        }

        if (null !== $jobFilterCriteria->jobId) {
            $select->where([
                'uuid' => $jobFilterCriteria->jobId,
            ]);
        }

        if (null !== $jobFilterCriteria->idempotencyKey) {
            $select->where([
                'idempotencyKey' => $jobFilterCriteria->idempotencyKey,
            ]);
        }

        if (null !== $jobFilterCriteria->deletionRunId) {
            $select->whereJson('payload->deletion_run_id', $jobFilterCriteria->deletionRunId);
        }

        if (null !== $jobFilterCriteria->sourceId) {
            $select->whereJson('payload->source_id', $jobFilterCriteria->sourceId);
        }

        if (null !== $jobFilterCriteria->workerId) {
            $select->where([
                'workerId' => $jobFilterCriteria->workerId,
            ]);
        }

        if ($jobFilterCriteria->createdFrom instanceof Chronos) {
            $select->where('createdAt', '>=', $jobFilterCriteria->createdFrom);
        }

        if ($jobFilterCriteria->createdTo instanceof Chronos) {
            $select->where('createdAt', '<=', $jobFilterCriteria->createdTo->endOfDay());
        }

        if ($jobFilterCriteria->scheduledFrom instanceof Chronos) {
            $select->where('scheduledAt', '>=', $jobFilterCriteria->scheduledFrom);
        }

        if ($jobFilterCriteria->scheduledTo instanceof Chronos) {
            $select->where('scheduledAt', '<=', $jobFilterCriteria->scheduledTo->endOfDay());
        }

        if (null !== $jobFilterCriteria->hasWarnings) {
            $select->where(new Fragment(
                'CAST(uuid AS TEXT) ' . ($jobFilterCriteria->hasWarnings ? 'IN' : 'NOT IN')
                . ' (SELECT job_id FROM job_logs WHERE level = ?)',
                JobLog::LEVEL_WARNING,
            ));
        }

        if (true === $jobFilterCriteria->hasError) {
            $select
                ->where('errorMessage', '!=', null)
                ->where('errorMessage', '!=', '')
            ;
        } elseif (false === $jobFilterCriteria->hasError) {
            $select->where(static function(QueryBuilder $queryBuilder): void {
                $queryBuilder
                    ->where('errorMessage', null)
                    ->orWhere('errorMessage', '')
                ;
            });
        }

        return $select;
    }

    /**
     * @param Select<Job>  $select
     * @param list<string> $statuses
     */
    private function applyStatusFilter(Select $select, array $statuses): void
    {
        if (1 === count($statuses)) {
            $select->where([
                'status' => $statuses[0],
            ]);

            return;
        }

        if ([] === $statuses) {
            return;
        }

        $select->where([
            'status' => [
                'in' => $statuses,
            ],
        ]);
    }

    /**
     * @return SelectQuery<array<string, mixed>>
     */
    private function filteredRowsSelect(JobFilterCriteria $jobFilterCriteria, bool $includeStatuses = true): SelectQuery
    {
        $selectQuery = $this->database()
            ->select()
            ->from(self::TABLE_NAME)
        ;

        if (null !== $jobFilterCriteria->organizationId) {
            $selectQuery->where('organization_id', $jobFilterCriteria->organizationId);
        }

        if ($includeStatuses) {
            if (1 === count($jobFilterCriteria->statuses)) {
                $selectQuery->where('status', $jobFilterCriteria->statuses[0]);
            } elseif ([] !== $jobFilterCriteria->statuses) {
                $selectQuery->where('status', 'in', $jobFilterCriteria->statuses);
            }
        }

        if (1 === count($jobFilterCriteria->types)) {
            $selectQuery->where('type', $jobFilterCriteria->types[0]);
        } elseif ([] !== $jobFilterCriteria->types) {
            $selectQuery->where('type', 'in', $jobFilterCriteria->types);
        }

        if (null !== $jobFilterCriteria->search) {
            $search = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $jobFilterCriteria->search) . '%';
            $selectQuery->where(new Fragment(
                '(CAST(uuid AS TEXT) LIKE ? OR type LIKE ? OR idempotency_key LIKE ?)',
                $search,
                $search,
                $search,
            ));
        }

        if (null !== $jobFilterCriteria->jobId) {
            $selectQuery->where('uuid', $jobFilterCriteria->jobId);
        }

        if (null !== $jobFilterCriteria->idempotencyKey) {
            $selectQuery->where('idempotency_key', $jobFilterCriteria->idempotencyKey);
        }

        if (null !== $jobFilterCriteria->deletionRunId) {
            $selectQuery->whereJson('payload->deletion_run_id', $jobFilterCriteria->deletionRunId);
        }

        if (null !== $jobFilterCriteria->sourceId) {
            $selectQuery->whereJson('payload->source_id', $jobFilterCriteria->sourceId);
        }

        if (null !== $jobFilterCriteria->workerId) {
            $selectQuery->where('worker_id', $jobFilterCriteria->workerId);
        }

        if ($jobFilterCriteria->createdFrom instanceof Chronos) {
            $selectQuery->where('created_at', '>=', $jobFilterCriteria->createdFrom);
        }

        if ($jobFilterCriteria->createdTo instanceof Chronos) {
            $selectQuery->where('created_at', '<=', $jobFilterCriteria->createdTo->endOfDay());
        }

        if ($jobFilterCriteria->scheduledFrom instanceof Chronos) {
            $selectQuery->where('scheduled_at', '>=', $jobFilterCriteria->scheduledFrom);
        }

        if ($jobFilterCriteria->scheduledTo instanceof Chronos) {
            $selectQuery->where('scheduled_at', '<=', $jobFilterCriteria->scheduledTo->endOfDay());
        }

        if (null !== $jobFilterCriteria->hasWarnings) {
            $selectQuery->where(new Fragment(
                'CAST(uuid AS TEXT) ' . ($jobFilterCriteria->hasWarnings ? 'IN' : 'NOT IN')
                . ' (SELECT job_id FROM job_logs WHERE level = ?)',
                JobLog::LEVEL_WARNING,
            ));
        }

        if (true === $jobFilterCriteria->hasError) {
            $selectQuery
                ->where('error_message', '!=', null)
                ->where('error_message', '!=', '')
            ;
        } elseif (false === $jobFilterCriteria->hasError) {
            $selectQuery->where(new Fragment("(error_message IS NULL OR error_message = '')"));
        }

        return $selectQuery;
    }

    /**
     * @return non-empty-string
     */
    private function globalSortField(?string $sortBy): string
    {
        return in_array($sortBy, self::ALLOWED_GLOBAL_SORT_FIELDS, true) ? $sortBy : 'createdAt';
    }
}
