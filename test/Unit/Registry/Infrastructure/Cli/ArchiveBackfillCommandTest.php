<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Cli;

use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Registry\Application\Dto\PackageData;
use Packio\Registry\Application\Dto\ReleaseData;
use Packio\Registry\Application\Port\PackageQueryPort;
use Packio\Registry\Application\Port\ReleaseQueryPort;
use Packio\Registry\Application\Port\ReleaseStorageMetadataPort;
use Packio\Registry\Application\Storage\ArchiveObjectMetadata;
use Packio\Registry\Application\Storage\PackageArchiveKeyGenerator;
use Packio\Registry\Infrastructure\Adapter\Worker\StoreReleaseArchiveJobHandler;
use Packio\Registry\Infrastructure\Cli\ArchiveBackfillCommand;
use Packio\Registry\Infrastructure\Cli\ArchiveDevCleanupCommand;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use PackioTest\Support\Builder\JobBuilder;
use PackioTest\Support\Fake\InMemoryPackageArchiveStorage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(ArchiveBackfillCommand::class)]
#[CoversClass(ArchiveDevCleanupCommand::class)]
#[AllowMockObjectsWithoutExpectations]
final class ArchiveBackfillCommandTest extends TestCase
{
    public function testDryRunPrintsMatchingPrivateReleaseWithoutEnqueueing(): void
    {
        $jobApiFacade = $this->createMock(JobApiFacadeInterface::class);
        $jobApiFacade->expects(self::never())->method('enqueueOnce');

        $archiveBackfillCommand = new ArchiveBackfillCommand(
            $this->organizationFacade(new OrganizationDto('org-1', 'acme', 'Acme')),
            new FakePackageQueryPort([
                $this->package('vendor/package', 'private'),
            ]),
            new FakeReleaseQueryPort([
                'vendor/package' => [$this->release('vendor/package', '1.0.0', 'ref-1')],
            ]),
            new FakeReleaseStorageMetadataPort(),
            $jobApiFacade,
        );

        $bufferedOutput = new BufferedOutput();
        $exitCode       = $archiveBackfillCommand->run(new ArrayInput([
            '--organization' => 'acme',
            '--dry-run'      => true,
        ]), $bufferedOutput);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('dry-run vendor/package 1.0.0', $bufferedOutput->fetch());
    }

    public function testSchedulesMissingPrivateArchivesAndSkipsPublicAndStoredReleases(): void
    {
        $jobApiFacade = $this->createMock(JobApiFacadeInterface::class);
        $jobApiFacade->expects(self::once())
            ->method('enqueueOnce')
            ->with(
                'org-1',
                StoreReleaseArchiveJobHandler::TYPE,
                'archive:backfill:org-1:vendor/private:1.1.0:ref-2',
                [
                    'organization_id' => 'org-1',
                    'package_name'    => 'vendor/private',
                    'version'         => '1.1.0',
                    'reference'       => 'ref-2',
                    'origin'          => 'backfill',
                ],
            )
            ->willReturn(JobBuilder::make([
                'organizationId' => 'org-1',
                'type'           => StoreReleaseArchiveJobHandler::TYPE,
                'payload'        => [],
            ]))
        ;

        $archiveBackfillCommand = new ArchiveBackfillCommand(
            $this->organizationFacade(new OrganizationDto('org-1', 'acme', 'Acme')),
            new FakePackageQueryPort([
                $this->package('vendor/private', 'private'),
                $this->package('vendor/public', 'public'),
            ]),
            new FakeReleaseQueryPort([
                'vendor/private' => [
                    $this->release('vendor/private', '1.0.0', 'ref-1'),
                    $this->release('vendor/private', '1.1.0', 'ref-2'),
                ],
                'vendor/public'  => [
                    $this->release('vendor/public', '1.0.0', 'ref-public'),
                ],
            ]),
            new FakeReleaseStorageMetadataPort([
                'vendor/private:1.0.0' => [
                    'key' => 'private/org/org-1/dist/vendor/private/1.0.0_ref-1.zip',
                ],
            ]),
            $jobApiFacade,
        );

        $bufferedOutput = new BufferedOutput();
        $exitCode       = $archiveBackfillCommand->run(new ArrayInput([
            '--organization' => 'acme',
        ]), $bufferedOutput);

        $display = $bufferedOutput->fetch();
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('skip stored vendor/private 1.0.0', $display);
        self::assertStringContainsString('scheduled vendor/private 1.1.0', $display);
        self::assertStringContainsString('skip public package vendor/public', $display);
    }

    public function testUnknownOrganizationFails(): void
    {
        $organizationApiFacade = $this->createMock(OrganizationApiFacadeInterface::class);
        $organizationApiFacade->expects(self::once())
            ->method('findOrganizationBySlug')
            ->with('missing')
            ->willReturn(null)
        ;
        $organizationApiFacade->expects(self::never())
            ->method('findOrganization')
        ;

        $archiveBackfillCommand = new ArchiveBackfillCommand(
            $organizationApiFacade,
            new FakePackageQueryPort(),
            new FakeReleaseQueryPort(),
            new FakeReleaseStorageMetadataPort(),
            $this->createMock(JobApiFacadeInterface::class),
        );

        $bufferedOutput = new BufferedOutput();
        $exitCode       = $archiveBackfillCommand->run(new ArrayInput([
            '--organization' => 'missing',
        ]), $bufferedOutput);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Organization "missing" was not found.', $bufferedOutput->fetch());
    }

    public function testOrganizationUuidFallsBackToIdLookup(): void
    {
        $organizationId        = '019df33a-10dc-7161-90e4-71b8248b5d9e';
        $organizationApiFacade = $this->createMock(OrganizationApiFacadeInterface::class);
        $organizationApiFacade->expects(self::once())
            ->method('findOrganizationBySlug')
            ->with($organizationId)
            ->willReturn(null)
        ;
        $organizationApiFacade->expects(self::once())
            ->method('findOrganization')
            ->with($organizationId)
            ->willReturn(new OrganizationDto($organizationId, 'acme', 'Acme'))
        ;

        $archiveBackfillCommand = new ArchiveBackfillCommand(
            $organizationApiFacade,
            new FakePackageQueryPort(),
            new FakeReleaseQueryPort(),
            new FakeReleaseStorageMetadataPort(),
            $this->createMock(JobApiFacadeInterface::class),
        );

        $bufferedOutput = new BufferedOutput();
        $exitCode       = $archiveBackfillCommand->run(new ArrayInput([
            '--organization' => $organizationId,
        ]), $bufferedOutput);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No packages matched.', $bufferedOutput->fetch());
    }

    public function testCleanupDryRunKeepsStaleDevArchiveObjects(): void
    {
        $inMemoryPackageArchiveStorage = $this->cleanupStorage();
        $archiveDevCleanupCommand      = $this->cleanupCommand($inMemoryPackageArchiveStorage);

        $bufferedOutput = new BufferedOutput();
        $exitCode       = $archiveDevCleanupCommand->run(new ArrayInput([
            '--organization' => 'acme',
        ]), $bufferedOutput);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('dry-run delete private/org/org-1/dist/vendor/private/dev-main_ref-old.zip', $bufferedOutput->fetch());
        self::assertTrue($inMemoryPackageArchiveStorage->exists('private/org/org-1/dist/vendor/private/dev-main_ref-old.zip'));
    }

    public function testCleanupDeletesOnlyStaleDevArchiveObjectsWhenConfirmed(): void
    {
        $inMemoryPackageArchiveStorage = $this->cleanupStorage();
        $archiveDevCleanupCommand      = $this->cleanupCommand($inMemoryPackageArchiveStorage);

        $bufferedOutput = new BufferedOutput();
        $exitCode       = $archiveDevCleanupCommand->run(new ArrayInput([
            '--organization' => 'acme',
            '--delete'       => true,
        ]), $bufferedOutput);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('deleted private/org/org-1/dist/vendor/private/dev-main_ref-old.zip', $bufferedOutput->fetch());
        self::assertFalse($inMemoryPackageArchiveStorage->exists('private/org/org-1/dist/vendor/private/dev-main_ref-old.zip'));
        self::assertFalse($inMemoryPackageArchiveStorage->exists('private/org/org-1/dist/vendor/private/2.0.x-dev_ref-old.zip'));
        self::assertTrue($inMemoryPackageArchiveStorage->exists('private/org/org-1/dist/vendor/private/dev-main_ref-current.zip'));
        self::assertTrue($inMemoryPackageArchiveStorage->exists('private/org/org-1/dist/vendor/private/1.0.0_ref-old.zip'));
    }

    private function organizationFacade(?OrganizationDto $organizationDto): OrganizationApiFacadeInterface
    {
        $organizationApiFacade = $this->createMock(OrganizationApiFacadeInterface::class);
        $organizationApiFacade->method('findOrganization')->willReturnCallback(
            static fn (string $id): ?OrganizationDto => $organizationDto instanceof OrganizationDto && $id === $organizationDto->id
                ? $organizationDto
                : null,
        );
        $organizationApiFacade->method('findOrganizationBySlug')->willReturnCallback(
            static fn (string $slug): ?OrganizationDto => $organizationDto instanceof OrganizationDto && $slug === $organizationDto->slug
                ? $organizationDto
                : null,
        );

        return $organizationApiFacade;
    }

    private function cleanupCommand(InMemoryPackageArchiveStorage $inMemoryPackageArchiveStorage): ArchiveDevCleanupCommand
    {
        return new ArchiveDevCleanupCommand(
            $this->organizationFacade(new OrganizationDto('org-1', 'acme', 'Acme')),
            new FakePackageQueryPort([
                $this->package('vendor/private', 'private'),
            ]),
            new FakeReleaseQueryPort([
                'vendor/private' => [
                    $this->release('vendor/private', 'dev-main', 'ref-current'),
                ],
            ]),
            $inMemoryPackageArchiveStorage,
            new PackageArchiveKeyGenerator('private'),
        );
    }

    private function cleanupStorage(): InMemoryPackageArchiveStorage
    {
        $inMemoryPackageArchiveStorage = new InMemoryPackageArchiveStorage();
        $inMemoryPackageArchiveStorage->put(
            'private/org/org-1/dist/vendor/private/dev-main_ref-current.zip',
            'current-dev',
            new ArchiveObjectMetadata(),
        );
        $inMemoryPackageArchiveStorage->put(
            'private/org/org-1/dist/vendor/private/dev-main_ref-old.zip',
            'old-dev',
            new ArchiveObjectMetadata(),
        );
        $inMemoryPackageArchiveStorage->put(
            'private/org/org-1/dist/vendor/private/2.0.x-dev_ref-old.zip',
            'old-branch-alias',
            new ArchiveObjectMetadata(),
        );
        $inMemoryPackageArchiveStorage->put(
            'private/org/org-1/dist/vendor/private/1.0.0_ref-old.zip',
            'old-stable',
            new ArchiveObjectMetadata(),
        );

        return $inMemoryPackageArchiveStorage;
    }

    private function package(string $name, string $visibility): PackageData
    {
        return new PackageData(
            id: $name,
            name: $name,
            type: 'library',
            sourceId: 'source-1',
            organizationId: 'org-1',
            active: true,
            visibility: $visibility,
            description: null,
        );
    }

    private function release(string $packageName, string $version, string $reference): ReleaseData
    {
        return new ReleaseData(
            id: $packageName . ':' . $version,
            version: $version,
            reference: $reference,
            active: true,
            composerJson: [],
            distMetadata: [],
        );
    }
}

final readonly class FakePackageQueryPort implements PackageQueryPort
{
    /** @param list<PackageData> $packages */
    public function __construct(private array $packages = []) {}

    public function allPackages(string $organizationId): array
    {
        return $this->packages;
    }

    public function findPackageInOrganization(string $organizationId, string $name): ?PackageData
    {
        foreach ($this->packages as $package) {
            if ($package->name === $name) {
                return $package;
            }
        }

        return null;
    }
}

final readonly class FakeReleaseQueryPort implements ReleaseQueryPort
{
    /** @param array<string, list<ReleaseData>> $releases */
    public function __construct(private array $releases = []) {}

    public function releasesForPackage(string $organizationId, string $packageName): array
    {
        return $this->releases[$packageName] ?? [];
    }

    public function findRelease(string $organizationId, string $packageName, string $version): ?ReleaseData
    {
        foreach ($this->releases[$packageName] ?? [] as $release) {
            if ($release->version === $version) {
                return $release;
            }
        }

        return null;
    }

    public function latestActiveVersions(string $organizationId): array
    {
        return [];
    }
}

final readonly class FakeReleaseStorageMetadataPort implements ReleaseStorageMetadataPort
{
    /** @param array<string, array<string, mixed>> $metadata */
    public function __construct(private array $metadata = []) {}

    public function findDistStorageMetadata(string $organizationId, string $packageName, string $version): ?array
    {
        return $this->metadata[$packageName . ':' . $version] ?? null;
    }

    public function distStorageKeysForPackage(string $organizationId, string $packageName): array
    {
        return [];
    }

    public function updateDistStorageMetadata(
        string $organizationId,
        string $packageName,
        string $version,
        array $storageData
    ): ?ReleaseData {
        return null;
    }
}
