<?php

declare(strict_types=1);

namespace PackioTest\Unit\Package\Application\Command;

use Packio\Package\Application\Command\DownloadCountTransactionInterface;
use Packio\Package\Application\Command\PackageCommandService;
use Packio\Package\Application\Command\PackageMaintenanceService;
use Packio\Package\Application\Deletion\PackageDeletionService;
use Packio\Package\Application\Port\PackageArchiveCleanupPort;
use Packio\Package\Application\Port\PackageReleaseCleanupPort;
use Packio\Package\Application\Port\ReleaseServicePort;
use Packio\Package\Domain\Entity\Package;
use Packio\Package\Domain\Exception\PackageSourceConflictException;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

use function array_filter;
use function array_values;
use function count;

#[CoversClass(PackageCommandService::class)]
final class PackageCommandServiceTest extends TestCase
{
    #[Test]
    public function upsertRejectsSamePackageNameFromDifferentSource(): void
    {
        $inMemoryPackageRepository = new InMemoryPackageRepository([
            Package::create(
                'org-1',
                'vendor/package',
                'library',
                'Existing package',
                'public',
                'source-1',
                'github',
                'https://github.com/vendor/package',
            ),
        ]);

        $packageCommandService = $this->createService($inMemoryPackageRepository);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Package "vendor/package" is already published from another source.',
        );

        $packageCommandService->upsert(
            'org-1',
            'vendor/package',
            'library',
            'Conflicting package',
            'public',
            'source-2',
            'gitlab',
            'https://gitlab.com/vendor/package',
        );
    }

    #[Test]
    public function upsertAllowsSamePackageNameFromSameSource(): void
    {
        $inMemoryPackageRepository = new InMemoryPackageRepository([
            Package::create(
                'org-1',
                'vendor/package',
                'library',
                'Existing package',
                'public',
                'source-1',
                'github',
                'https://github.com/vendor/package',
            ),
        ]);

        $packageCommandService = $this->createService($inMemoryPackageRepository);

        $package = $packageCommandService->upsert(
            'org-1',
            'vendor/package',
            'project',
            'Updated package',
            'private',
            'source-1',
            'github',
            'https://github.com/vendor/package',
        );

        self::assertSame('source-1', $package->sourceId());
        self::assertSame('project', $package->type());
        self::assertSame('Updated package', $package->description());
        self::assertSame('public', $package->visibility());
    }

    private function createService(PackageRepositoryInterface $packageRepository): PackageCommandService
    {
        return new PackageCommandService(
            $packageRepository,
            new PackageMaintenanceService($packageRepository),
            new class implements ReleaseServicePort {
                public function incrementDownloadCount(string $packageName, string $version, string $organizationId): bool
                {
                    return false;
                }
            },
            new PackageDeletionService(
                new PackageMaintenanceService($packageRepository),
                $this->createStub(PackageArchiveCleanupPort::class),
                $this->createStub(PackageReleaseCleanupPort::class),
            ),
            $this->createStub(DownloadCountTransactionInterface::class),
        );
    }
}

final class InMemoryPackageRepository implements PackageRepositoryInterface
{
    /** @param list<Package> $packages */
    public function __construct(private array $packages = []) {}

    public function save(Package $package): Package
    {
        foreach ($this->packages as $index => $existing) {
            if (
                $existing->organizationId() === $package->organizationId()
                && $existing->name() === $package->name()
            ) {
                $this->packages[$index] = $package;

                return $package;
            }
        }

        $this->packages[] = $package;

        return $package;
    }

    public function incrementDownloadCount(string $organizationId, string $name): bool
    {
        return false;
    }

    public function upsertByNaturalKey(Package $package): Package
    {
        $existing = $this->findByName($package->name(), $package->organizationId());
        if (! $existing instanceof Package) {
            return $this->save($package);
        }

        if ('' !== $existing->sourceId() && '' !== $package->sourceId() && $existing->sourceId() !== $package->sourceId()) {
            throw new PackageSourceConflictException($package->name());
        }

        $existing->updateMetadata(
            $package->type(),
            $package->description(),
            $existing->visibility(),
            $package->sourceId(),
            $package->sourceType(),
            $package->sourceUrl(),
        );
        if (null !== $package->readmeHtml()) {
            $existing->updateReadmeHtml($package->readmeHtml());
        }

        return $this->save($existing);
    }

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void {}

    public function findByName(string $name, string $organizationId): ?Package
    {
        foreach ($this->packages as $package) {
            if ($package->name() !== $name) {
                continue;
            }

            if ($package->organizationId() !== $organizationId) {
                continue;
            }

            return $package;
        }

        return null;
    }

    public function findByOrganization(string $organizationId): array
    {
        return array_values(array_filter(
            $this->packages,
            static fn (Package $package): bool => $package->organizationId() === $organizationId,
        ));
    }

    public function findAll(): array
    {
        return $this->packages;
    }

    public function countAll(): int
    {
        return count($this->packages);
    }

    public function countForOrganization(string $organizationId): int
    {
        return count($this->findByOrganization($organizationId));
    }
}
