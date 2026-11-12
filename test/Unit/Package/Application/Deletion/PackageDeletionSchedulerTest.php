<?php

declare(strict_types=1);

namespace PackioTest\Unit\Package\Application\Deletion;

use Packio\Package\Application\Deletion\PackageDeletionScheduler;
use Packio\Package\Application\Port\JobSchedulerPort;
use Packio\Package\Application\Query\PackageQueryService;
use Packio\Package\Domain\Entity\Package;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackageDeletionScheduler::class)]
final class PackageDeletionSchedulerTest extends TestCase
{
    #[Test]
    public function itSchedulesOrganizationRunDeletion(): void
    {
        $jobSchedulerPort = $this->createMock(JobSchedulerPort::class);
        $jobSchedulerPort->expects(self::once())->method('enqueueOnce')->with(
            'org-1',
            'package.delete',
            'organization-delete:org-1:run-1:package:vendor/package',
            [
                'organization_id' => 'org-1',
                'package_name'    => 'vendor/package',
                'deletion_run_id' => 'run-1',
                'origin'          => 'organization.delete',
            ],
            10,
        );

        self::assertTrue($this->scheduler($this->existingPackage(), $jobSchedulerPort)->schedule('org-1', 'vendor/package', 'run-1', 'organization.delete'));
    }

    #[Test]
    public function itSchedulesStandaloneDeletionWithTrimmedInput(): void
    {
        $jobSchedulerPort = $this->createMock(JobSchedulerPort::class);
        $jobSchedulerPort->expects(self::once())->method('enqueueOnce')->with(
            'org-1',
            'package.delete',
            'package-delete:org-1:vendor/package',
            [
                'organization_id' => 'org-1',
                'package_name'    => 'vendor/package',
            ],
            10,
        );

        self::assertTrue($this->scheduler($this->existingPackage(), $jobSchedulerPort)->schedule(' org-1 ', ' vendor/package '));
    }

    #[Test]
    public function itSkipsMissingPackage(): void
    {
        $jobSchedulerPort = $this->createMock(JobSchedulerPort::class);
        $jobSchedulerPort->expects(self::never())->method('enqueueOnce');

        self::assertFalse($this->scheduler(null, $jobSchedulerPort)->schedule('org-1', 'vendor/package'));
    }

    #[Test]
    public function itSkipsBlankInput(): void
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->expects(self::never())->method('findByName');
        $jobSchedulerPort = $this->createMock(JobSchedulerPort::class);
        $jobSchedulerPort->expects(self::never())->method('enqueueOnce');
        $packageDeletionScheduler = new PackageDeletionScheduler(new PackageQueryService($repository), $jobSchedulerPort);

        self::assertFalse($packageDeletionScheduler->schedule('', 'vendor/package'));
        self::assertFalse($packageDeletionScheduler->schedule('org-1', '  '));
    }

    private function existingPackage(): Package
    {
        return Package::create('org-1', 'vendor/package', 'library', '', 'public', '', 'github', '');
    }

    private function scheduler(?Package $package, JobSchedulerPort $jobSchedulerPort): PackageDeletionScheduler
    {
        $repository = $this->createStub(PackageRepositoryInterface::class);
        $repository->method('findByName')->willReturn($package);

        return new PackageDeletionScheduler(new PackageQueryService($repository), $jobSchedulerPort);
    }
}
