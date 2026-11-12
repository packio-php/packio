<?php

declare(strict_types=1);

namespace PackioTest\Integration\Release\Infrastructure\Facade;

use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PackioTest\Support\Builder\ReleaseBuilder;
use PHPUnit\Framework\Attributes\Test;

use function ksort;

final class ReleaseLatestActiveVersionsTest extends IntegrationTestCase
{
    private const string ORG_A = 'org-latest-a';
    private const string ORG_B = 'org-latest-b';

    #[Test]
    public function latestActiveVersionsPicksHighestActiveVersionPerPackage(): void
    {
        $this->seed();

        $latest = $this->facade()->latestActiveVersions(self::ORG_A);
        ksort($latest);

        self::assertSame(
            [
                'vendor/one'   => '1.10.0',
                'vendor/three' => 'dev-main',
                'vendor/two'   => '2.0.0',
            ],
            $latest,
        );
    }

    #[Test]
    public function latestActiveVersionsMatchesReleasesForPackageHead(): void
    {
        $this->seed();

        $facade = $this->facade();
        $latest = $facade->latestActiveVersions(self::ORG_A);

        foreach (['vendor/one', 'vendor/two', 'vendor/three'] as $name) {
            self::assertSame($facade->releasesForPackage($name, false, self::ORG_A)[0]->version, $latest[$name]);
        }

        self::assertArrayNotHasKey('vendor/four', $latest);
        self::assertSame([], $facade->releasesForPackage('vendor/four', false, self::ORG_A));
    }

    private function facade(): ReleaseApiFacadeInterface
    {
        $facade = $this->getService(ReleaseApiFacadeInterface::class);
        self::assertInstanceOf(ReleaseApiFacadeInterface::class, $facade);

        return $facade;
    }

    private function seed(): void
    {
        $facade = $this->facade();
        foreach ([
            ['vendor/one', '1.0.0'], ['vendor/one', '1.9.0'], ['vendor/one', '1.10.0'], ['vendor/one', 'dev-main'],
            ['vendor/two', '2.0.0'], ['vendor/two', '3.0.0'],
            ['vendor/three', 'dev-main'],
            ['vendor/four', '1.0.0'],
        ] as [$name, $version]) {
            $facade->publish(ReleaseBuilder::make([
                'packageName'    => $name,
                'version'        => $version,
                'organizationId' => self::ORG_A,
            ]));
        }
        $facade->publish(ReleaseBuilder::make([
            'packageName'    => 'vendor/one',
            'version'        => '9.0.0',
            'organizationId' => self::ORG_B,
        ]));

        $repository = $this->getService(ReleaseRepositoryInterface::class);
        self::assertInstanceOf(ReleaseRepositoryInterface::class, $repository);
        foreach ([['vendor/two', '3.0.0'], ['vendor/four', '1.0.0']] as [$name, $version]) {
            $release = $repository->findByPackageAndVersion($name, $version, self::ORG_A);
            self::assertInstanceOf(Release::class, $release);
            $release->deactivate();
            $repository->save($release);
        }
    }
}
