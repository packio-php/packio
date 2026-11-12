<?php

declare(strict_types=1);

namespace Packio\Worker\Application\Command;

use Cake\Chronos\Chronos;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;

final readonly class WorkerMaintenanceService
{
    public function __construct(private JobRepositoryInterface $jobRepository, private JobLogRepositoryInterface $jobLogRepository) {}

    public function cleanupOldJobs(int $retentionDays = 1): int
    {
        $threshold = new Chronos("-{$retentionDays} days");

        return $this->jobRepository->deleteOlderThan($threshold);
    }

    public function cleanupOldJobLogs(int $retentionDays = 1): int
    {
        $threshold = new Chronos("-{$retentionDays} days");

        return $this->jobLogRepository->deleteOlderThan($threshold);
    }
}
