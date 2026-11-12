<?php

declare(strict_types=1);

namespace PackioTest\Unit\Release\Application\Command;

use Cake\Chronos\Chronos;
use InvalidArgumentException;
use Packio\Release\Application\Command\ReleaseCommandService;
use Packio\Release\Application\Command\ReleaseMaintenanceService;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use PackioTest\Support\Base\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use RuntimeException;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

#[AllowMockObjectsWithoutExpectations]
final class ReleaseCommandServiceTest extends UnitTestCase
{
    public function testUpdatesOnlyDistStorageMetadata(): void
    {
        $release = Release::create(
            organizationId: 'org-1',
            packageName: 'vendor/package',
            version: '1.0.0',
            reference: 'ref',
            composerData: [
                'name' => 'vendor/package',
            ],
            sourceData: [
                'type' => 'git',
            ],
            distData: [
                'type'      => 'zip',
                'url'       => 'https://source.example/archive.zip',
                'reference' => 'ref',
            ],
        );
        $repository = new class($release) implements ReleaseRepositoryInterface {
            use ReleaseRepositoryTestDoubleTrait;

            public ?Release $saved = null;

            public function __construct(private readonly Release $release) {}

            public function save(Release $release): Release
            {
                $this->saved = $release;

                return $release;
            }

            public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void {}

            public function findByPackageAndVersion(string $packageName, string $version, string $organizationId): ?Release
            {
                return 'vendor/package' === $packageName && '1.0.0' === $version && 'org-1' === $organizationId
                    ? $this->release
                    : null;
            }

            public function findPackageKeys(): array
            {
                return [];
            }

            public function findActiveVersionsByOrganization(string $organizationId): array
            {
                return [];
            }

            public function findByPackageName(string $packageName, string $organizationId): array
            {
                return [];
            }
        };

        $updated = $this->service($repository)->updateDistStorageMetadata(
            'vendor/package',
            '1.0.0',
            'org-1',
            $this->storageData(),
        );

        self::assertSame($release, $updated);
        self::assertSame($release, $repository->saved);
        self::assertSame([
            'type'      => 'zip',
            'url'       => 'https://source.example/archive.zip',
            'reference' => 'ref',
            'storage'   => [
                'driver'           => 'local',
                'bucket'           => null,
                'key'              => 'private/org/org-1/dist/vendor/package/1.0.0_ref.zip',
                'content_type'     => 'application/zip',
                'size'             => 13,
                'sha256'           => 'abc123',
                'etag'             => '"abc123"',
                'stored_at'        => '2026-06-10T12:00:00+00:00',
                'source_provider'  => 'github',
                'source_reference' => 'ref',
            ],
        ], $release->distData());
    }

    public function testRejectsInvalidDistStorageMetadata(): void
    {
        $release = Release::create(
            organizationId: 'org-1',
            packageName: 'vendor/package',
            version: '1.0.0',
            reference: 'ref',
            composerData: [
                'name' => 'vendor/package',
            ],
            sourceData: [
                'type' => 'git',
            ],
            distData: [
                'type' => 'zip',
            ],
        );
        $repository = new class($release) implements ReleaseRepositoryInterface {
            use ReleaseRepositoryTestDoubleTrait;

            public ?Release $saved = null;

            public function __construct(private readonly Release $release) {}

            public function save(Release $release): Release
            {
                $this->saved = $release;

                return $release;
            }

            public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void {}

            public function findByPackageAndVersion(string $packageName, string $version, string $organizationId): ?Release
            {
                return 'vendor/package' === $packageName && '1.0.0' === $version && 'org-1' === $organizationId
                    ? $this->release
                    : null;
            }

            public function findPackageKeys(): array
            {
                return [];
            }

            public function findActiveVersionsByOrganization(string $organizationId): array
            {
                return [];
            }

            public function findByPackageName(string $packageName, string $organizationId): array
            {
                return [];
            }
        };

        $this->expectException(InvalidArgumentException::class);

        try {
            $this->service($repository)->updateDistStorageMetadata(
                'vendor/package',
                '1.0.0',
                'org-1',
                [
                    'driver' => 'local',
                    'key'    => 'private/archive.zip',
                ],
            );
        } finally {
            self::assertNull($repository->saved);
            self::assertSame([
                'type' => 'zip',
            ], $release->distData());
        }
    }

    public function testRejectsDistStorageMetadataWithoutSourceReference(): void
    {
        $repository = $this->createMock(ReleaseRepositoryInterface::class);
        $repository->expects(self::never())->method('updateDistStorageMetadataByNaturalKey');
        $storageData                     = $this->storageData();
        $storageData['source_reference'] = null;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Release dist storage source reference is required.');

        $this->service($repository)->updateDistStorageMetadata(
            'vendor/package',
            '1.0.0',
            'org-1',
            $storageData,
        );
    }

    public function testPublishPreservesExistingStorageMetadataWhenReferenceIsUnchanged(): void
    {
        $release = Release::create(
            organizationId: 'org-1',
            packageName: 'vendor/package',
            version: '1.0.0',
            reference: 'ref',
            composerData: [
                'name' => 'vendor/package',
            ],
            sourceData: [
                'type' => 'git',
            ],
            distData: [
                'type'      => 'zip',
                'url'       => 'https://source.example/archive.zip',
                'reference' => 'ref',
                'storage'   => $this->storageData(),
            ],
        );
        $repository = new readonly class($release) implements ReleaseRepositoryInterface {
            use ReleaseRepositoryTestDoubleTrait;

            public function __construct(private Release $release) {}

            public function save(Release $release): Release
            {
                return $release;
            }

            public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void {}

            public function findByPackageAndVersion(string $packageName, string $version, string $organizationId): ?Release
            {
                return 'vendor/package' === $packageName && '1.0.0' === $version && 'org-1' === $organizationId
                    ? $this->release
                    : null;
            }

            public function findPackageKeys(): array
            {
                return [];
            }

            public function findActiveVersionsByOrganization(string $organizationId): array
            {
                return [];
            }

            public function findByPackageName(string $packageName, string $organizationId): array
            {
                return [];
            }
        };

        $this->service($repository)->publish(
            'org-1',
            'vendor/package',
            '1.0.0',
            'ref',
            [
                'name'        => 'vendor/package',
                'description' => 'Updated',
            ],
            [
                'type' => 'git',
            ],
            [
                'type'      => 'zip',
                'url'       => 'https://source.example/archive-updated.zip',
                'reference' => 'ref',
            ],
            '2024-04-01T12:00:00+00:00',
        );

        self::assertSame($this->storageData(), $release->distData()['storage']);
        self::assertSame('2024-04-01T12:00:00+00:00', $release->releasedAt()?->format(DATE_ATOM));
    }

    public function testPublishStoresReleaseDate(): void
    {
        $repository = new class implements ReleaseRepositoryInterface {
            use ReleaseRepositoryTestDoubleTrait;

            public ?Release $saved = null;

            public function save(Release $release): Release
            {
                $this->saved = $release;

                return $release;
            }

            public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void {}

            public function findByPackageAndVersion(string $packageName, string $version, string $organizationId): ?Release
            {
                return null;
            }

            public function findPackageKeys(): array
            {
                return [];
            }

            public function findActiveVersionsByOrganization(string $organizationId): array
            {
                return [];
            }

            public function findByPackageName(string $packageName, string $organizationId): array
            {
                return [];
            }
        };

        $release = $this->service($repository)->publish(
            'org-1',
            'vendor/package',
            '1.0.0',
            'ref',
            [
                'name' => 'vendor/package',
            ],
            [
                'type' => 'git',
            ],
            [
                'type' => 'zip',
            ],
            '2024-04-02T15:30:00+03:00',
        );

        self::assertSame($release, $repository->saved);
        self::assertSame('2024-04-02T12:30:00+00:00', $release->releasedAt()?->format(DATE_ATOM));
    }

    public function testPublishPreservesExistingReleaseDateWhenNoNewReleaseDateIsProvided(): void
    {
        $release = Release::create(
            organizationId: 'org-1',
            packageName: 'vendor/package',
            version: '1.0.0',
            reference: 'ref',
            composerData: [
                'name' => 'vendor/package',
            ],
            sourceData: [
                'type' => 'git',
            ],
            distData: [
                'type' => 'zip',
            ],
            releasedAt: new Chronos('2024-04-03T12:00:00+00:00'),
        );
        $repository = new readonly class($release) implements ReleaseRepositoryInterface {
            use ReleaseRepositoryTestDoubleTrait;

            public function __construct(private Release $release) {}

            public function save(Release $release): Release
            {
                return $release;
            }

            public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void {}

            public function findByPackageAndVersion(string $packageName, string $version, string $organizationId): ?Release
            {
                return 'vendor/package' === $packageName && '1.0.0' === $version && 'org-1' === $organizationId
                    ? $this->release
                    : null;
            }

            public function findPackageKeys(): array
            {
                return [];
            }

            public function findActiveVersionsByOrganization(string $organizationId): array
            {
                return [];
            }

            public function findByPackageName(string $packageName, string $organizationId): array
            {
                return [];
            }
        };

        $this->service($repository)->publish(
            'org-1',
            'vendor/package',
            '1.0.0',
            'ref',
            [
                'name' => 'vendor/package',
            ],
            [
                'type' => 'git',
            ],
            [
                'type' => 'zip',
            ],
        );

        self::assertSame('2024-04-03T12:00:00+00:00', $release->releasedAt()?->format(DATE_ATOM));
    }

    public function testPublishDropsExistingStorageMetadataWhenReferenceChanges(): void
    {
        $release = Release::create(
            organizationId: 'org-1',
            packageName: 'vendor/package',
            version: '1.0.0',
            reference: 'old-ref',
            composerData: [
                'name' => 'vendor/package',
            ],
            sourceData: [
                'type' => 'git',
            ],
            distData: [
                'type'      => 'zip',
                'url'       => 'https://source.example/archive.zip',
                'reference' => 'old-ref',
                'storage'   => $this->storageData(),
            ],
        );
        $repository = new readonly class($release) implements ReleaseRepositoryInterface {
            use ReleaseRepositoryTestDoubleTrait;

            public function __construct(private Release $release) {}

            public function save(Release $release): Release
            {
                return $release;
            }

            public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void {}

            public function findByPackageAndVersion(string $packageName, string $version, string $organizationId): ?Release
            {
                return 'vendor/package' === $packageName && '1.0.0' === $version && 'org-1' === $organizationId
                    ? $this->release
                    : null;
            }

            public function findPackageKeys(): array
            {
                return [];
            }

            public function findActiveVersionsByOrganization(string $organizationId): array
            {
                return [];
            }

            public function findByPackageName(string $packageName, string $organizationId): array
            {
                return [];
            }
        };

        $this->service($repository)->publish(
            'org-1',
            'vendor/package',
            '1.0.0',
            'new-ref',
            [
                'name' => 'vendor/package',
            ],
            [
                'type' => 'git',
            ],
            [
                'type'      => 'zip',
                'url'       => 'https://source.example/archive-new.zip',
                'reference' => 'new-ref',
            ],
        );

        self::assertArrayNotHasKey('storage', $release->distData());
    }

    public function testPublishStoresUpstreamReferenceAndReferenceType(): void
    {
        $repository = new class implements ReleaseRepositoryInterface {
            use ReleaseRepositoryTestDoubleTrait;

            public ?Release $saved = null;

            public function save(Release $release): Release
            {
                $this->saved = $release;

                return $release;
            }

            public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void {}

            public function findByPackageAndVersion(string $packageName, string $version, string $organizationId): ?Release
            {
                return null;
            }

            public function findPackageKeys(): array
            {
                return [];
            }

            public function findActiveVersionsByOrganization(string $organizationId): array
            {
                return [];
            }

            public function findByPackageName(string $packageName, string $organizationId): array
            {
                return [];
            }
        };

        $release = $this->service($repository)->publish(
            'org-1',
            'vendor/package',
            '2.x-dev',
            '2222222222222222222222222222222222222222',
            [
                'name' => 'vendor/package',
            ],
            [
                'type'      => 'git',
                'reference' => '2222222222222222222222222222222222222222',
            ],
            [
                'type'      => 'zip',
                'reference' => '2222222222222222222222222222222222222222',
            ],
            upstreamReference: '2.x',
            referenceType: 'branch',
        );

        self::assertSame($release, $repository->saved);
        self::assertSame('2222222222222222222222222222222222222222', $release->reference());
        self::assertSame('2.x', $release->upstreamReference());
        self::assertSame('branch', $release->referenceType());
    }

    public function testUpdateDistStorageMetadataRequiresOrganization(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service($this->createMock(ReleaseRepositoryInterface::class))
            ->updateDistStorageMetadata('vendor/package', '1.0.0', '', [])
        ;
    }

    private function service(ReleaseRepositoryInterface $releaseRepository): ReleaseCommandService
    {
        return new ReleaseCommandService(
            $releaseRepository,
            new ReleaseMaintenanceService($releaseRepository),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function storageData(): array
    {
        return [
            'driver'           => 'local',
            'bucket'           => null,
            'key'              => 'private/org/org-1/dist/vendor/package/1.0.0_ref.zip',
            'content_type'     => 'application/zip',
            'size'             => 13,
            'sha256'           => 'abc123',
            'etag'             => '"abc123"',
            'stored_at'        => '2026-06-10T12:00:00+00:00',
            'source_provider'  => 'github',
            'source_reference' => 'ref',
        ];
    }
}

trait ReleaseRepositoryTestDoubleTrait
{
    public function incrementDownloadCount(string $organizationId, string $packageName, string $version): bool
    {
        return false;
    }

    public function upsertByNaturalKey(Release $release): Release
    {
        $existing = $this->findByPackageAndVersion(
            $release->packageName(),
            $release->version(),
            $release->organizationId(),
        );
        if (! $existing instanceof Release) {
            return $this->save($release);
        }

        $existing->updatePayload(
            $release->reference(),
            $release->composerData(),
            $release->sourceData(),
            $release->distData(),
            $release->releasedAt(),
            $release->upstreamReference(),
            $release->referenceType(),
        );

        return $this->save($existing);
    }

    public function updateDistStorageMetadataByNaturalKey(
        string $organizationId,
        string $packageName,
        string $version,
        string $expectedReference,
        array $storageData,
    ): ?Release {
        $release = $this->findByPackageAndVersion($packageName, $version, $organizationId);
        if (! $release instanceof Release || $release->reference() !== $expectedReference) {
            return null;
        }

        $release->updateDistStorageMetadata($storageData);

        return $this->save($release);
    }
}
