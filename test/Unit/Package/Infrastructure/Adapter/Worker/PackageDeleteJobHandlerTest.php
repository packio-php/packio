<?php

declare(strict_types=1);

namespace PackioTest\Unit\Package\Infrastructure\Adapter\Worker;

use Packio\Package\Application\Command\PackageMaintenanceService;
use Packio\Package\Application\Deletion\PackageDeletionService;
use Packio\Package\Application\Port\PackageArchiveCleanupPort;
use Packio\Package\Application\Port\PackageReleaseCleanupPort;
use Packio\Package\Domain\Entity\Package;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use Packio\Package\Infrastructure\Adapter\Worker\PackageDeleteJobHandler;
use Packio\Worker\Api\Job\JobLoggerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackageDeleteJobHandler::class)]
final class PackageDeleteJobHandlerTest extends TestCase
{
    #[Test]
    #[DataProvider('outcomes')]
    public function itMapsOutcomes(string $outcome, bool $success, ?string $error): void
    {
        $package    = Package::create('org-1', 'vendor/package', 'library', '', 'public', '', 'github', '');
        $repository = $this->createStub(PackageRepositoryInterface::class);
        $repository->method('findByName')->willReturn(
            'already_deleted' === $outcome ? null : $package,
            'retryable_failure' === $outcome ? null : $package,
        );
        $archive = $this->createMock(PackageArchiveCleanupPort::class);
        $archive->expects('already_deleted' === $outcome ? self::never() : self::once())
            ->method('schedulePackageArchiveCleanup')
            ->with('org-1', 'vendor/package', 'run-1', 'organization.delete')
            ->willReturn([
                'discovered' => 1,
                'scheduled'  => 1,
                'failed'     => 'blocked' === $outcome ? 1 : 0,
            ])
        ;
        $release = $this->createStub(PackageReleaseCleanupPort::class);
        $release->method('deleteReleasesForPackage')->willReturn(2);
        $packageDeleteJobHandler = new PackageDeleteJobHandler(new PackageDeletionService(new PackageMaintenanceService($repository), $archive, $release));
        $logger                  = $this->createMock(JobLoggerInterface::class);
        $logger->expects($success ? self::never() : self::once())->method('warning')->with(
            $error,
            self::callback(static fn (array $data): bool => 'run-1' === $data['deletion_run_id'] && ! isset($data['token'])),
        );

        $jobResult = $packageDeleteJobHandler->handle([
            'organization_id' => 'org-1',
            'package_name'    => 'vendor/package',
            'deletion_run_id' => ' run-1 ',
            'origin'          => ' organization.delete ',
            'token'           => 'secret',
        ], $logger);

        self::assertSame($success, $jobResult->isSuccess());
        self::assertSame($error, $jobResult->getErrorMessage());
        self::assertSame('org-1', $jobResult->getData()['organization_id']);
        self::assertSame('vendor/package', $jobResult->getData()['package_name']);
        self::assertSame('run-1', $jobResult->getData()['deletion_run_id']);
        self::assertSame('deleted' === $outcome, $jobResult->getData()['deleted']);
        self::assertArrayNotHasKey('token', $jobResult->getData());
        if (! $success) {
            self::assertTrue($jobResult->shouldRetry());
        }
    }

    /** @return iterable<string, array{string, bool, ?string}> */
    public static function outcomes(): iterable
    {
        yield 'deleted' => ['deleted', true, null];

        yield 'already deleted' => ['already_deleted', true, null];

        yield 'retryable failure' => ['retryable_failure', false, 'Package could not be deleted.'];

        yield 'blocked' => ['blocked', false, 'Archive cleanup could not be fully scheduled.'];
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('invalidPayloads')]
    public function itRejectsInvalidPayloadWithoutRetry(array $payload): void
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->expects(self::never())->method('findByName');
        $packageDeleteJobHandler = new PackageDeleteJobHandler(new PackageDeletionService(
            new PackageMaintenanceService($repository),
            $this->createStub(PackageArchiveCleanupPort::class),
            $this->createStub(PackageReleaseCleanupPort::class),
        ));

        $jobResult = $packageDeleteJobHandler->handle($payload, $this->createStub(JobLoggerInterface::class));

        self::assertFalse($jobResult->isSuccess());
        self::assertFalse($jobResult->shouldRetry());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): iterable
    {
        yield 'missing organization' => [[
            'package_name' => 'vendor/package',
        ]];

        yield 'blank organization' => [[
            'organization_id' => ' ',
            'package_name'    => 'vendor/package',
        ]];

        yield 'invalid organization' => [[
            'organization_id' => 1,
            'package_name'    => 'vendor/package',
        ]];

        yield 'missing package' => [[
            'organization_id' => 'org-1',
        ]];

        yield 'blank package' => [[
            'organization_id' => 'org-1',
            'package_name'    => ' ',
        ]];

        yield 'invalid package' => [[
            'organization_id' => 'org-1',
            'package_name'    => [],
        ]];
    }
}
