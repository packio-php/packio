<?php

declare(strict_types=1);

namespace PackioTest\Unit\Package\Application\Deletion;

use Packio\Package\Application\Command\PackageMaintenanceService;
use Packio\Package\Application\Deletion\PackageDeletionOutcome;
use Packio\Package\Application\Deletion\PackageDeletionService;
use Packio\Package\Application\Port\PackageArchiveCleanupPort;
use Packio\Package\Application\Port\PackageReleaseCleanupPort;
use Packio\Package\Domain\Entity\Package;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

use function in_array;
use function serialize;

#[CoversClass(PackageDeletionService::class)]
final class PackageDeletionServiceTest extends TestCase
{
    #[Test]
    public function itResumesAfterReleasesWereDeletedButPackageDeletionFailed(): void
    {
        $package    = Package::create('org-1', 'vendor/package', 'library', '', 'public', '', 'github', '');
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->expects(self::exactly(5))->method('findByName')->willReturn($package, $package, $package, $package, null);
        $attempts = 0;
        $repository->expects(self::exactly(2))->method('deleteAndFlush')->willReturnCallback(
            static function() use (&$attempts): void {
                if (1 === ++$attempts) {
                    throw new RuntimeException('Temporary database failure.');
                }
            },
        );
        $archive = $this->createMock(PackageArchiveCleanupPort::class);
        $archive->expects(self::exactly(2))->method('schedulePackageArchiveCleanup')
            ->with('org-1', 'vendor/package', 'run-1', '')
            ->willReturn(
                [
                    'discovered' => 1,
                    'scheduled'  => 1,
                    'failed'     => 0,
                ],
                [
                    'discovered' => 0,
                    'scheduled'  => 0,
                    'failed'     => 0,
                ],
            )
        ;
        $release = $this->createMock(PackageReleaseCleanupPort::class);
        $release->expects(self::exactly(2))->method('deleteReleasesForPackage')->willReturn(2, 0);
        $packageDeletionService = new PackageDeletionService(new PackageMaintenanceService($repository), $archive, $release);

        $packageDeletionResult  = $packageDeletionService->delete('org-1', 'vendor/package', 'run-1');
        $second                 = $packageDeletionService->delete('org-1', 'vendor/package', 'run-1');
        $third                  = $packageDeletionService->delete('org-1', 'vendor/package', 'run-1');

        self::assertSame(PackageDeletionOutcome::RetryableFailure, $packageDeletionResult->outcome);
        self::assertSame(2, $packageDeletionResult->data['releases_deleted']);
        self::assertSame(PackageDeletionOutcome::Deleted, $second->outcome);
        self::assertSame(0, $second->data['releases_deleted']);
        self::assertSame(PackageDeletionOutcome::AlreadyDeleted, $third->outcome);
    }

    #[Test]
    public function itRetriesPartiallyScheduledCleanupBeforeRemovingMetadata(): void
    {
        $package    = Package::create('org-1', 'vendor/package', 'library', '', 'public', '', 'github', '');
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('findByName')->willReturn($package);
        $repository->expects(self::once())->method('deleteAndFlush');
        $archive = $this->createMock(PackageArchiveCleanupPort::class);
        $archive->expects(self::exactly(2))->method('schedulePackageArchiveCleanup')
            ->with('org-1', 'vendor/package', null, '')
            ->willReturn(
                [
                    'discovered' => 101,
                    'scheduled'  => 1,
                    'failed'     => 1,
                ],
                [
                    'discovered' => 101,
                    'scheduled'  => 2,
                    'failed'     => 0,
                ],
            )
        ;
        $release = $this->createMock(PackageReleaseCleanupPort::class);
        $release->expects(self::once())->method('deleteReleasesForPackage')->willReturn(101);
        $packageDeletionService = new PackageDeletionService(new PackageMaintenanceService($repository), $archive, $release);

        self::assertSame(PackageDeletionOutcome::Blocked, $packageDeletionService->delete('org-1', 'vendor/package', '')->outcome);
        self::assertSame(PackageDeletionOutcome::Deleted, $packageDeletionService->delete('org-1', 'vendor/package', '')->outcome);
    }

    #[Test]
    #[DataProvider('failures')]
    public function itContainsFailuresWithoutLeakingSecrets(
        string $step,
        PackageDeletionOutcome $packageDeletionOutcome,
        string $message
    ): void {
        $repository            = $this->createMock(PackageRepositoryInterface::class);
        $archive               = $this->createMock(PackageArchiveCleanupPort::class);
        $release               = $this->createMock(PackageReleaseCleanupPort::class);
        $runtimeException      = new RuntimeException('password=secret');
        $invocationStubber     = $repository->expects(self::exactly('package' === $step ? 2 : 1))->method('findByName')
            ->with('vendor/package', 'org-1')
        ;
        if ('lookup' === $step) {
            $invocationStubber->willThrowException($runtimeException);
        } else {
            $invocationStubber->willReturn(Package::create('org-1', 'vendor/package', 'library', '', 'public', '', 'github', ''));
        }
        $cleanup = $archive->expects('lookup' === $step ? self::never() : self::once())
            ->method('schedulePackageArchiveCleanup')->with('org-1', 'vendor/package', 'run-1', 'organization.delete')
        ;
        if ('archive' === $step) {
            $cleanup->willThrowException($runtimeException);
        } else {
            $cleanup->willReturn([
                'discovered' => 0,
                'scheduled'  => 0,
                'failed'     => 0,
            ]);
        }
        $releases = $release->expects(in_array($step, ['release', 'package'], true) ? self::once() : self::never())
            ->method('deleteReleasesForPackage')->with('vendor/package', 'org-1')
        ;
        if ('release' === $step) {
            $releases->willThrowException($runtimeException);
        } else {
            $releases->willReturn(0);
        }
        $repository->expects('package' === $step ? self::once() : self::never())
            ->method('deleteAndFlush')->willThrowException($runtimeException)
        ;
        $packageDeletionService = new PackageDeletionService(new PackageMaintenanceService($repository), $archive, $release);

        $packageDeletionResult = $packageDeletionService->delete('org-1', 'vendor/package', 'run-1', 'organization.delete');

        self::assertSame($packageDeletionOutcome, $packageDeletionResult->outcome);
        self::assertSame($message, $packageDeletionResult->message);
        self::assertSame('run-1', $packageDeletionResult->data['deletion_run_id']);
        self::assertStringNotContainsString('secret', serialize($packageDeletionResult));
    }

    /** @return iterable<string, array{string, PackageDeletionOutcome, string}> */
    public static function failures(): iterable
    {
        yield 'lookup' => ['lookup', PackageDeletionOutcome::RetryableFailure, 'Package existence could not be checked.'];

        yield 'archive' => ['archive', PackageDeletionOutcome::Blocked, 'Archive cleanup could not be scheduled.'];

        yield 'release' => ['release', PackageDeletionOutcome::RetryableFailure, 'Package releases could not be deleted.'];

        yield 'package' => ['package', PackageDeletionOutcome::RetryableFailure, 'Package could not be deleted.'];
    }

    #[Test]
    public function itRetriesWhenPackageDisappearsDuringDeletion(): void
    {
        $packageDeleteOperationRecorder   = new PackageDeleteOperationRecorder();
        $packageDeleteRepositoryStub      = new PackageDeleteRepositoryStub(
            Package::create('org-1', 'vendor/package', 'library', '', 'public', '', 'github', ''),
            $packageDeleteOperationRecorder,
            disappearAfterLookup: true,
        );
        $packageDeletionService = new PackageDeletionService(
            new PackageMaintenanceService($packageDeleteRepositoryStub),
            new PackageDeleteArchiveCleanupPortStub($packageDeleteOperationRecorder),
            new PackageDeleteReleaseCleanupPortStub($packageDeleteOperationRecorder),
        );

        $packageDeletionResult = $packageDeletionService->delete('org-1', 'vendor/package', 'run-1');

        self::assertSame(PackageDeletionOutcome::RetryableFailure, $packageDeletionResult->outcome);
        self::assertSame('Package could not be deleted.', $packageDeletionResult->message);
        self::assertSame('org-1', $packageDeletionResult->data['organization_id']);
        self::assertSame('vendor/package', $packageDeletionResult->data['package_name']);
        self::assertSame('run-1', $packageDeletionResult->data['deletion_run_id']);
        self::assertTrue($packageDeletionResult->data['package_found']);
        self::assertFalse($packageDeletionResult->data['deleted']);
        self::assertArrayNotHasKey('token', $packageDeletionResult->data);
        self::assertSame(PackageDeletionOutcome::AlreadyDeleted, $packageDeletionService->delete('org-1', 'vendor/package', 'run-1')->outcome);
    }

    #[Test]
    public function itSchedulesArchiveCleanupBeforeDeletingReleasesAndPackage(): void
    {
        $packageDeleteOperationRecorder = new PackageDeleteOperationRecorder();
        $package                        = Package::create('org-1', 'vendor/package', 'library', '', 'public', '', 'github', '');

        $packageDeleteRepositoryStub = new PackageDeleteRepositoryStub($package, $packageDeleteOperationRecorder);
        $packageDeletionService      = new PackageDeletionService(
            new PackageMaintenanceService($packageDeleteRepositoryStub),
            new PackageDeleteArchiveCleanupPortStub($packageDeleteOperationRecorder),
            new PackageDeleteReleaseCleanupPortStub($packageDeleteOperationRecorder),
        );

        $packageDeletionResult = $packageDeletionService->delete('org-1', 'vendor/package', 'run-1', 'organization.delete');

        self::assertSame(PackageDeletionOutcome::Deleted, $packageDeletionResult->outcome);
        self::assertSame([
            'archive_cleanup',
            'release_cleanup',
            'package_delete',
        ], $packageDeleteOperationRecorder->events);
        self::assertNull($packageDeleteRepositoryStub->package);
        self::assertSame(2, $packageDeletionResult->data['releases_deleted'] ?? null);
        self::assertSame(1, $packageDeletionResult->data['archive_cleanup_scheduled'] ?? null);
        self::assertSame(PackageDeletionOutcome::AlreadyDeleted, $packageDeletionService->delete('org-1', 'vendor/package', 'run-1')->outcome);
        self::assertCount(3, $packageDeleteOperationRecorder->events);
    }

    #[Test]
    public function itDoesNotDeleteMetadataWhenArchiveCleanupFails(): void
    {
        $packageDeleteOperationRecorder = new PackageDeleteOperationRecorder();
        $package                        = Package::create('org-1', 'vendor/package', 'library', '', 'public', '', 'github', '');

        $packageDeleteRepositoryStub = new PackageDeleteRepositoryStub($package, $packageDeleteOperationRecorder);
        $packageDeletionService      = new PackageDeletionService(
            new PackageMaintenanceService($packageDeleteRepositoryStub),
            new PackageDeleteArchiveCleanupPortStub($packageDeleteOperationRecorder, [
                'discovered' => 1,
                'scheduled'  => 0,
                'failed'     => 1,
            ]),
            new PackageDeleteReleaseCleanupPortStub($packageDeleteOperationRecorder),
        );

        $packageDeletionResult = $packageDeletionService->delete('org-1', 'vendor/package', 'run-1', 'organization.delete');

        self::assertSame(PackageDeletionOutcome::Blocked, $packageDeletionResult->outcome);
        self::assertSame('Archive cleanup could not be fully scheduled.', $packageDeletionResult->message);
        self::assertSame(['archive_cleanup'], $packageDeleteOperationRecorder->events);
        self::assertSame($package, $packageDeleteRepositoryStub->package);
    }

    #[Test]
    public function itTreatsMissingPackageAsAlreadyDeleted(): void
    {
        $packageDeleteOperationRecorder = new PackageDeleteOperationRecorder();
        $packageDeletionService         = new PackageDeletionService(
            new PackageMaintenanceService(new PackageDeleteRepositoryStub(null, $packageDeleteOperationRecorder)),
            new PackageDeleteArchiveCleanupPortStub($packageDeleteOperationRecorder),
            new PackageDeleteReleaseCleanupPortStub($packageDeleteOperationRecorder),
        );

        $packageDeletionResult = $packageDeletionService->delete('org-1', 'vendor/package', 'run-1', 'organization.delete');

        self::assertSame(PackageDeletionOutcome::AlreadyDeleted, $packageDeletionResult->outcome);
        self::assertSame([], $packageDeleteOperationRecorder->events);
        self::assertTrue($packageDeletionResult->data['already_deleted'] ?? false);
    }
}

final class PackageDeleteOperationRecorder
{
    /** @var list<string> */
    public array $events = [];
}

final readonly class PackageDeleteArchiveCleanupPortStub implements PackageArchiveCleanupPort
{
    /**
     * @param array{discovered: int, scheduled: int, failed: int} $result
     */
    public function __construct(
        private PackageDeleteOperationRecorder $packageDeleteOperationRecorder,
        private array $result = ['discovered' => 1, 'scheduled' => 1, 'failed' => 0],
        private bool $shouldThrow = false,
    ) {}

    public function schedulePackageArchiveCleanup(
        string $organizationId,
        string $packageName,
        ?string $deletionRunId = null,
        string $origin = '',
    ): array {
        unset($organizationId, $packageName, $deletionRunId, $origin);

        $this->packageDeleteOperationRecorder->events[] = 'archive_cleanup';

        if ($this->shouldThrow) {
            throw new RuntimeException('Archive cleanup unavailable.');
        }

        return $this->result;
    }
}

final readonly class PackageDeleteReleaseCleanupPortStub implements PackageReleaseCleanupPort
{
    public function __construct(private PackageDeleteOperationRecorder $packageDeleteOperationRecorder) {}

    public function deleteReleasesForPackage(string $packageName, string $organizationId): int
    {
        unset($packageName, $organizationId);

        $this->packageDeleteOperationRecorder->events[] = 'release_cleanup';

        return 2;
    }
}

final class PackageDeleteRepositoryStub implements PackageRepositoryInterface
{
    public function __construct(
        public ?Package $package,
        private readonly PackageDeleteOperationRecorder $packageDeleteOperationRecorder,
        private readonly bool $disappearAfterLookup = false,
    ) {}

    public function save(Package $package): Package
    {
        $this->package = $package;

        return $package;
    }

    public function incrementDownloadCount(string $organizationId, string $name): bool
    {
        return false;
    }

    public function upsertByNaturalKey(Package $package): Package
    {
        return $this->save($package);
    }

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void
    {
        unset($entity, $cascade);

        $this->packageDeleteOperationRecorder->events[] = 'package_delete';
        $this->package                                  = null;
    }

    public function findByName(string $name, string $organizationId): ?Package
    {
        unset($name, $organizationId);

        $package = $this->package;
        if ($this->disappearAfterLookup) {
            $this->package = null;
        }

        return $package;
    }

    public function findByOrganization(string $organizationId): array
    {
        unset($organizationId);

        return $this->package instanceof Package ? [$this->package] : [];
    }

    public function findAll(): array
    {
        return $this->package instanceof Package ? [$this->package] : [];
    }

    public function countAll(): int
    {
        return $this->package instanceof Package ? 1 : 0;
    }

    public function countForOrganization(string $organizationId): int
    {
        return $this->package instanceof Package && $this->package->organizationId() === $organizationId ? 1 : 0;
    }
}
