<?php

declare(strict_types=1);

namespace Packio\Worker\Domain\Repository;

use Cake\Chronos\Chronos;
use Packio\Worker\Domain\Entity\JobLog;

interface JobLogRepositoryInterface
{
    public function save(JobLog $jobLog): void;

    /**
     * @return list<JobLog>
     */
    public function findByJob(string $jobId): array;

    /**
     * @param list<string> $jobIds
     *
     * @return array<string, int>
     */
    public function countWarningsByJobIds(array $jobIds): array;

    /**
     * Delete job logs older than the specified date.
     *
     * @return int Number of deleted logs
     */
    public function deleteOlderThan(Chronos $threshold): int;
}
