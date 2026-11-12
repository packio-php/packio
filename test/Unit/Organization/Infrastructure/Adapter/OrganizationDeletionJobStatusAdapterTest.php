<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Infrastructure\Adapter;

use Packio\Organization\Infrastructure\Adapter\OrganizationDeletionJobStatusAdapter;
use Packio\Worker\Api\JobApiFacadeInterface;
use PackioTest\Support\Builder\JobBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class OrganizationDeletionJobStatusAdapterTest extends TestCase
{
    /** @var list<string> */
    private const array CLEANUP_JOB_TYPES = [
        'source.delete',
        'package.delete',
        'cleanup.package.archives',
    ];

    #[Test]
    public function itLoadsRunningOrPendingCleanupJobsByDeletionRunWithoutSamplingLimit(): void
    {
        $jobDto = JobBuilder::make([
            'id'             => 'job-1',
            'organizationId' => 'org-1',
            'type'           => 'package.delete',
            'status'         => 'pending',
        ]);

        $jobApiFacade = $this->jobApiFacade();
        $jobApiFacade
            ->expects(self::once())
            ->method('findByOrganizationDeletionRun')
            ->with('org-1', 'run-1', self::CLEANUP_JOB_TYPES, ['pending', 'running'])
            ->willReturn([$jobDto])
        ;

        $organizationDeletionJobStatusAdapter = new OrganizationDeletionJobStatusAdapter($jobApiFacade);

        self::assertSame(
            [
                [
                    'id'            => 'job-1',
                    'type'          => 'package.delete',
                    'status'        => 'pending',
                    'error_message' => null,
                ],
            ],
            $organizationDeletionJobStatusAdapter->runningOrPendingCleanupJobs('org-1', 'run-1'),
        );
    }

    #[Test]
    public function itLoadsFailedCleanupJobsByDeletionRunWithoutSamplingLimit(): void
    {
        $jobDto = JobBuilder::make([
            'id'             => 'job-2',
            'organizationId' => 'org-1',
            'type'           => 'cleanup.package.archives',
            'status'         => 'failed',
            'errorMessage'   => 'Archive cleanup failed',
        ]);

        $jobApiFacade = $this->jobApiFacade();
        $jobApiFacade
            ->expects(self::once())
            ->method('findByOrganizationDeletionRun')
            ->with('org-1', 'run-1', self::CLEANUP_JOB_TYPES, ['failed'])
            ->willReturn([$jobDto])
        ;

        $organizationDeletionJobStatusAdapter = new OrganizationDeletionJobStatusAdapter($jobApiFacade);

        self::assertSame(
            [
                [
                    'id'            => 'job-2',
                    'type'          => 'cleanup.package.archives',
                    'status'        => 'failed',
                    'error_message' => 'Archive cleanup failed',
                ],
            ],
            $organizationDeletionJobStatusAdapter->failedCleanupJobs('org-1', 'run-1'),
        );
    }

    private function jobApiFacade(): JobApiFacadeInterface&MockObject
    {
        return $this->createMock(JobApiFacadeInterface::class);
    }
}
