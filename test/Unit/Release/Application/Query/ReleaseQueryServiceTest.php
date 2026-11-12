<?php

declare(strict_types=1);

namespace PackioTest\Unit\Release\Application\Query;

use Packio\Release\Application\Query\ReleaseQueryService;
use Packio\Release\Application\Service\ReleaseVersionComparator;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ReleaseQueryService::class)]
#[AllowMockObjectsWithoutExpectations]
final class ReleaseQueryServiceTest extends TestCase
{
    #[Test]
    public function releasesForPackageRequiresOrganization(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Organization is required to query releases.');

        $this->service()->releasesForPackage('vendor/package', false, '');
    }

    #[Test]
    public function findReleaseRequiresOrganization(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Organization is required to query releases.');

        $this->service()->findRelease('vendor/package', '1.0.0', '');
    }

    #[Test]
    public function latestActiveVersionsRequiresOrganization(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Organization is required to query releases.');

        $this->service()->latestActiveVersions('');
    }

    #[Test]
    public function latestActiveVersionsUsesComparatorOrder(): void
    {
        $repository = $this->createStub(ReleaseRepositoryInterface::class);
        $repository->method('findActiveVersionsByOrganization')->willReturn([
            [
                'package_name' => 'vendor/one',
                'version'      => '1.0.0',
            ],
            [
                'package_name' => 'vendor/one',
                'version'      => 'dev-main',
            ],
            [
                'package_name' => 'vendor/one',
                'version'      => '1.10.0',
            ],
            [
                'package_name' => 'vendor/one',
                'version'      => '1.9.0',
            ],
        ]);

        self::assertSame([
            'vendor/one' => '1.10.0',
        ], $this->service($repository)->latestActiveVersions('org'));
    }

    #[Test]
    public function distStorageKeysForPackageIncludesInactiveReleases(): void
    {
        $release = $this->release('2.0.0', [
            'storage' => [
                'key' => 'k2',
            ],
        ]);
        $release->deactivate();
        $repository = $this->createStub(ReleaseRepositoryInterface::class);
        $repository->method('findByPackageName')->willReturn([
            $this->release('1.0.0', [
                'storage' => [
                    'key' => 'k1',
                ],
            ]),
            $release,
        ]);

        self::assertSame(['k1', 'k2'], $this->service($repository)->distStorageKeysForPackage('vendor/package', 'org'));
    }

    #[Test]
    public function distStorageKeysForPackageSkipsReleasesWithoutStorageKey(): void
    {
        $repository = $this->createStub(ReleaseRepositoryInterface::class);
        $repository->method('findByPackageName')->willReturn([
            $this->release('1.0.0', []),
            $this->release('1.1.0', [
                'storage' => [
                    'driver' => 'local',
                ],
            ]),
            $this->release('1.2.0', [
                'storage' => [
                    'key' => '',
                ],
            ]),
        ]);

        self::assertSame([], $this->service($repository)->distStorageKeysForPackage('vendor/package', 'org'));
    }

    #[Test]
    public function distStorageKeysForPackageRequiresOrganization(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Organization is required to query releases.');

        $this->service()->distStorageKeysForPackage('vendor/package', '');
    }

    /**
     * @param array<string, mixed> $distData
     */
    private function release(string $version, array $distData): Release
    {
        return Release::create('org', 'vendor/package', $version, 'ref', [], [], $distData);
    }

    private function service(?ReleaseRepositoryInterface $releaseRepository = null): ReleaseQueryService
    {
        return new ReleaseQueryService(
            $releaseRepository ?? $this->createMock(ReleaseRepositoryInterface::class),
            new ReleaseVersionComparator(),
        );
    }
}
