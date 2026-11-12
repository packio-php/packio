<?php

declare(strict_types=1);

namespace PackioTest\Unit\Package\Infrastructure\Adapter\Worker;

use Packio\Package\Application\Command\DownloadCountTransactionInterface;
use Packio\Package\Application\Command\PackageCommandService;
use Packio\Package\Application\Command\PackageMaintenanceService;
use Packio\Package\Application\Deletion\PackageDeletionService;
use Packio\Package\Application\Port\PackageArchiveCleanupPort;
use Packio\Package\Application\Port\PackageReleaseCleanupPort;
use Packio\Package\Application\Port\ReleaseServicePort;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use Packio\Package\Infrastructure\Adapter\Worker\IncrementDownloadCountJobHandler;
use Packio\Worker\Api\Job\JobLoggerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncrementDownloadCountJobHandler::class)]
final class IncrementDownloadCountJobHandlerTest extends TestCase
{
    private const array PAYLOAD = [
        'organization_id' => 'org-1',
        'package_name'    => 'vendor/package',
        'version'         => '1.0.0',
    ];

    #[Test]
    public function incrementsAndSucceeds(): void
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->expects(self::once())->method('incrementDownloadCount')->with('org-1', 'vendor/package')->willReturn(true);
        $release = $this->createMock(ReleaseServicePort::class);
        $release->expects(self::once())->method('incrementDownloadCount')->with('vendor/package', '1.0.0', 'org-1')->willReturn(false);

        $jobResult = $this->handler($repository, $release)->handle(self::PAYLOAD, $this->createStub(JobLoggerInterface::class));

        self::assertTrue($jobResult->isSuccess());
        self::assertSame([
            'package' => 'vendor/package',
            'version' => '1.0.0',
        ], $jobResult->getData());
    }

    #[Test]
    public function unknownPackageFailsWithoutRetry(): void
    {
        $repository = $this->createStub(PackageRepositoryInterface::class);
        $repository->method('incrementDownloadCount')->willReturn(false);
        $release = $this->createMock(ReleaseServicePort::class);
        $release->expects(self::never())->method('incrementDownloadCount');

        $jobResult = $this->handler($repository, $release)->handle(self::PAYLOAD, $this->createStub(JobLoggerInterface::class));

        self::assertFalse($jobResult->isSuccess());
        self::assertFalse($jobResult->shouldRetry());
        self::assertSame('Package not found.', $jobResult->getErrorMessage());
    }

    #[Test]
    public function missingVersionFails(): void
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->expects(self::never())->method('incrementDownloadCount');

        $jobResult = $this->handler($repository, $this->createStub(ReleaseServicePort::class))->handle(
            [
                'organization_id' => 'org-1',
                'package_name'    => 'vendor/package',
            ],
            $this->createStub(JobLoggerInterface::class),
        );

        self::assertFalse($jobResult->isSuccess());
        self::assertSame('Version is required.', $jobResult->getErrorMessage());
    }

    private function handler(
        PackageRepositoryInterface $packageRepository,
        ReleaseServicePort $releaseServicePort
    ): IncrementDownloadCountJobHandler {
        $transaction = $this->createStub(DownloadCountTransactionInterface::class);
        $transaction->method('run')->willReturnCallback(static fn (callable $callback): bool => $callback());

        return new IncrementDownloadCountJobHandler(new PackageCommandService(
            $packageRepository,
            new PackageMaintenanceService($packageRepository),
            $releaseServicePort,
            new PackageDeletionService(
                new PackageMaintenanceService($packageRepository),
                $this->createStub(PackageArchiveCleanupPort::class),
                $this->createStub(PackageReleaseCleanupPort::class),
            ),
            $transaction,
        ));
    }
}
