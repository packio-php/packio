<?php

declare(strict_types=1);

namespace Packio\Worker\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\Injection\Fragment;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function array_fill_keys;
use function array_key_exists;
use function is_scalar;
use function iterator_to_array;

/**
 * @extends AbstractWriteRepository<JobLog>
 */
final class JobLogRepository extends AbstractWriteRepository implements JobLogRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string TABLE_NAME   = 'job_logs';
    private const string ENTITY_CLASS = JobLog::class;

    /**
     * @throws RepositoryPersistException
     */
    public function save(JobLog $jobLog): void
    {
        $this->persistAndFlush($jobLog);
    }

    public function findByJob(string $jobId): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'jobId' => $jobId,
                ])
                ->orderBy([
                    'createdAt' => 'ASC',
                    'uuid'      => 'ASC',
                ])
                ->fetchAll(),
            false,
        );
    }

    public function countWarningsByJobIds(array $jobIds): array
    {
        if ([] === $jobIds) {
            return [];
        }

        $warningCounts = array_fill_keys($jobIds, 0);

        $rows = $this->database()
            ->select('job_id')
            ->columns([
                'job_id',
                new Fragment('COUNT(*) AS warning_count'),
            ])
            ->from(self::TABLE_NAME)
            ->where('job_id', 'in', $jobIds)
            ->where('level', JobLog::LEVEL_WARNING)
            ->groupBy('job_id')
            ->fetchAll()
        ;

        foreach ($rows as $row) {
            if (! isset($row['job_id'], $row['warning_count'])) {
                continue;
            }

            if (! is_scalar($row['job_id']) || ! is_scalar($row['warning_count'])) {
                continue;
            }

            $jobId = (string) $row['job_id'];
            if (array_key_exists($jobId, $warningCounts)) {
                $warningCounts[$jobId] = (int) $row['warning_count'];
            }
        }

        return $warningCounts;
    }

    public function deleteOlderThan(Chronos $threshold): int
    {
        return $this->database()
            ->delete(self::TABLE_NAME, [
                'created_at' => [
                    '<' => $threshold,
                ],
            ])
            ->run()
        ;
    }
}
