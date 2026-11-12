<?php

declare(strict_types=1);

namespace PackioTest\Integration\Worker\Infrastructure\Persistence\Cycle;

use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Infrastructure\Persistence\Cycle\JobLogRepository;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class JobLogRepositoryWarningCountTest extends IntegrationTestCase
{
    #[Test]
    public function itCountsOnlyWarningsForEveryRequestedJobId(): void
    {
        $jobLogRepository = $this->getService(JobLogRepositoryInterface::class);
        self::assertInstanceOf(JobLogRepository::class, $jobLogRepository);

        $firstJobId  = Uuid::uuid7()->toString();
        $secondJobId = Uuid::uuid7()->toString();
        $thirdJobId  = Uuid::uuid7()->toString();

        $jobLogRepository->save(JobLog::record($firstJobId, JobLog::LEVEL_WARNING, 'First warning.'));
        $jobLogRepository->save(JobLog::record($firstJobId, JobLog::LEVEL_WARNING, 'Second warning.'));
        $jobLogRepository->save(JobLog::record($firstJobId, JobLog::LEVEL_INFO, 'Informational entry.'));
        $jobLogRepository->save(JobLog::record($firstJobId, JobLog::LEVEL_ERROR, 'Error entry.'));
        $jobLogRepository->save(JobLog::record($secondJobId, JobLog::LEVEL_WARNING, 'Another warning.'));

        self::assertSame([
            $firstJobId  => 2,
            $secondJobId => 1,
            $thirdJobId  => 0,
        ], $jobLogRepository->countWarningsByJobIds([$firstJobId, $secondJobId, $thirdJobId]));
    }

    #[Test]
    public function itReturnsAnEmptyResultForAnEmptyJobIdList(): void
    {
        $jobLogRepository = $this->getService(JobLogRepositoryInterface::class);
        self::assertInstanceOf(JobLogRepository::class, $jobLogRepository);

        self::assertSame([], $jobLogRepository->countWarningsByJobIds([]));
    }
}
