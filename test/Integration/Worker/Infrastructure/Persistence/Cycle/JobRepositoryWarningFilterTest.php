<?php

declare(strict_types=1);

namespace PackioTest\Integration\Worker\Infrastructure\Persistence\Cycle;

use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Worker\Api\Dto\JobDto;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Api\JobApiFacadeInterface;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function array_map;
use function sort;

final class JobRepositoryWarningFilterTest extends IntegrationTestCase
{
    #[Test]
    public function itFiltersJobsWithWarnings(): void
    {
        [$organizationId, $withWarning]            = $this->seed();
        $facade                                    = $this->getService(JobApiFacadeInterface::class);
        $jobFilterCriteria                         = new JobFilterCriteria(organizationId: $organizationId, hasWarnings: true);

        $result = $facade->findPaginatedAll(new PaginationCriteria(), $jobFilterCriteria);

        self::assertSame([$withWarning], $this->ids($result->items));
        self::assertSame(1, $facade->countAllByStatus($jobFilterCriteria)['pending'] ?? null);
    }

    #[Test]
    public function itFiltersJobsWithoutWarnings(): void
    {
        [$organizationId, , $withInfo, $withoutLogs]            = $this->seed();
        $facade                                                 = $this->getService(JobApiFacadeInterface::class);
        $jobFilterCriteria                                      = new JobFilterCriteria(organizationId: $organizationId, hasWarnings: false);

        $result   = $facade->findPaginatedAll(new PaginationCriteria(), $jobFilterCriteria);
        $expected = [$withInfo, $withoutLogs];
        sort($expected);

        self::assertSame($expected, $this->ids($result->items));
        self::assertSame(2, $facade->countAllByStatus($jobFilterCriteria)['pending'] ?? null);
    }

    /**
     * @return array{string, string, string, string}
     */
    private function seed(): array
    {
        $organizationId = Uuid::uuid7()->toString();
        $facade         = $this->getService(JobApiFacadeInterface::class);
        $logs           = $this->getService(JobLogRepositoryInterface::class);

        $withWarning = $facade->enqueue($organizationId, 'test.warning', [])->id;
        $withInfo    = $facade->enqueue($organizationId, 'test.info', [])->id;
        $withoutLogs = $facade->enqueue($organizationId, 'test.none', [])->id;

        $logs->save(JobLog::record($withWarning, JobLog::LEVEL_WARNING, 'Warning.'));
        $logs->save(JobLog::record($withInfo, JobLog::LEVEL_INFO, 'Info.'));

        return [$organizationId, $withWarning, $withInfo, $withoutLogs];
    }

    /**
     * @param list<JobDto> $items
     *
     * @return list<string>
     */
    private function ids(array $items): array
    {
        $ids = array_map(static fn (JobDto $jobDto): string => $jobDto->id, $items);
        sort($ids);

        return $ids;
    }
}
