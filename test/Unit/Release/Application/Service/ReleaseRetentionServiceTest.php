<?php

declare(strict_types=1);

namespace PackioTest\Unit\Release\Application\Service;

use Packio\Release\Application\Service\ReleaseRetentionService;
use Packio\Release\Application\Service\ReleaseVersionComparator;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;

use function array_filter;
use function array_values;

#[CoversClass(ReleaseRetentionService::class)]
final class ReleaseRetentionServiceTest extends TestCase
{
    #[Test]
    public function zeroLimitDoesNotChangeReleaseActivity(): void
    {
        $release10 = $this->release('1.0.0', 'source-1');
        $release20 = $this->release('2.0.0', 'source-1');
        $release10->deactivate();

        $releaseRetentionResultDto = $this->service([$release10, $release20])
            ->applyForSourcePackage('org-1', 'source-1', 'vendor/package', 0)
        ;

        self::assertFalse($release10->isActive());
        self::assertTrue($release20->isActive());
        self::assertSame(0, $releaseRetentionResultDto->limit);
        self::assertSame(['2.0.0', '1.0.0'], $releaseRetentionResultDto->keptVersions);
        self::assertSame([], $releaseRetentionResultDto->deactivatedVersions);
    }

    #[Test]
    public function positiveLimitKeepsNewestStableVersionsAndDevReleases(): void
    {
        $release100 = $this->release('1.0.0', 'source-1');
        $release200 = $this->release('2.0.0', 'source-1');
        $release110 = $this->release('1.1.0', 'source-1');
        $release    = $this->release('dev-main', 'source-1');
        $devAlias   = $this->release('2.0.x-dev', 'source-1');

        $releaseRetentionResultDto = $this->service([$release100, $release200, $release110, $release, $devAlias])
            ->applyForSourcePackage('org-1', 'source-1', 'vendor/package', 2)
        ;

        self::assertFalse($release100->isActive());
        self::assertTrue($release110->isActive());
        self::assertTrue($release200->isActive());
        self::assertTrue($release->isActive());
        self::assertTrue($devAlias->isActive());
        self::assertSame(['2.0.0', '1.1.0'], $releaseRetentionResultDto->keptVersions);
        self::assertSame(['1.0.0'], $releaseRetentionResultDto->deactivatedVersions);
        self::assertSame(['dev-main', '2.0.x-dev'], $releaseRetentionResultDto->ignoredDevVersions);
    }

    #[Test]
    public function keptReleasesAreNotReactivated(): void
    {
        $release100 = $this->release('1.0.0', 'source-1', active: false);
        $release110 = $this->release('1.1.0', 'source-1');
        $release200 = $this->release('2.0.0', 'source-1');

        $releaseRetentionResultDto = $this->service([$release100, $release110, $release200])
            ->applyForSourcePackage('org-1', 'source-1', 'vendor/package', 3)
        ;

        self::assertFalse($release100->isActive());
        self::assertSame(['2.0.0', '1.1.0', '1.0.0'], $releaseRetentionResultDto->keptVersions);
        self::assertSame([], $releaseRetentionResultDto->deactivatedVersions);
    }

    #[Test]
    public function devAliasesAreNotReactivated(): void
    {
        $release = $this->release('2.0.x-dev', 'source-1', active: false);

        $releaseRetentionResultDto = $this->service([$release])
            ->applyForSourcePackage('org-1', 'source-1', 'vendor/package', 1)
        ;

        self::assertFalse($release->isActive());
        self::assertSame([], $releaseRetentionResultDto->keptVersions);
        self::assertSame(['2.0.x-dev'], $releaseRetentionResultDto->ignoredDevVersions);
    }

    #[Test]
    public function sourceIdFilterDoesNotTouchOtherSourceReleases(): void
    {
        $sourceRelease      = $this->release('1.0.0', 'source-1');
        $otherSourceRelease = $this->release('0.1.0', 'source-2', active: false);

        $this->service([$sourceRelease, $otherSourceRelease])
            ->applyForSourcePackage('org-1', 'source-1', 'vendor/package', 0)
        ;

        self::assertTrue($sourceRelease->isActive());
        self::assertFalse($otherSourceRelease->isActive());
    }

    /**
     * @param list<Release> $releases
     */
    private function service(array $releases): ReleaseRetentionService
    {
        return new ReleaseRetentionService(
            new ReleaseRepositoryStub($releases),
            new ReleaseVersionComparator(),
        );
    }

    private function release(string $version, string $sourceId, bool $active = true): Release
    {
        return new Release(
            organizationId: 'org-1',
            packageName: 'vendor/package',
            version: $version,
            reference: $version,
            active: $active,
            sourceData: [
                'source_id' => $sourceId,
                'type'      => 'git',
            ],
        );
    }
}

final readonly class ReleaseRepositoryStub implements ReleaseRepositoryInterface
{
    /**
     * @param list<Release> $releases
     */
    public function __construct(private array $releases) {}

    public function save(Release $release): Release
    {
        return $release;
    }

    public function incrementDownloadCount(string $organizationId, string $packageName, string $version): bool
    {
        return false;
    }

    public function upsertByNaturalKey(Release $release): Release
    {
        return $this->save($release);
    }

    public function updateDistStorageMetadataByNaturalKey(
        string $organizationId,
        string $packageName,
        string $version,
        string $expectedReference,
        array $storageData,
    ): ?Release {
        unset($organizationId, $packageName, $version, $expectedReference, $storageData);

        return null;
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
        return array_values(array_filter(
            $this->releases,
            static fn (Release $release): bool => $release->packageName() === $packageName
                && $release->organizationId() === $organizationId,
        ));
    }
}
