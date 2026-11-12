<?php

declare(strict_types=1);

namespace PackioTest\Integration\Release\Infrastructure\Cli;

use Closure;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use Packio\Release\Infrastructure\Cli\ReleaseOrphansCleanupCommand;
use PackioTest\Support\Base\IntegrationTestCase;
use PackioTest\Support\Builder\ReleaseBuilder;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class ReleaseOrphansCleanupCommandTest extends IntegrationTestCase
{
    private const string ORG = 'org-orphans';

    #[Test]
    public function itSchedulesArchiveCleanupAndDeletesOnlyReleasesWithoutAPackage(): void
    {
        $this->seed();
        $scheduled = [];

        $output = $this->prune([], $scheduled);

        self::assertStringContainsString('found=2 deleted=2', $output);
        self::assertSame([[self::ORG, 'vendor/orphan']], $scheduled);
        self::assertSame([], $this->facade()->releasesForPackage('vendor/orphan', true, self::ORG));
        self::assertCount(1, $this->facade()->releasesForPackage('vendor/kept', true, self::ORG));
    }

    #[Test]
    public function dryRunChangesNothing(): void
    {
        $this->seed();
        $scheduled = [];

        $output = $this->prune([
            '--dry-run' => true,
        ], $scheduled);

        self::assertStringContainsString('found=2 deleted=0', $output);
        self::assertSame([], $scheduled);
        self::assertCount(2, $this->facade()->releasesForPackage('vendor/orphan', true, self::ORG));
        self::assertCount(1, $this->facade()->releasesForPackage('vendor/kept', true, self::ORG));
    }

    #[Test]
    public function itKeepsReleasesWhenArchiveCleanupCannotBeScheduled(): void
    {
        $this->seed();
        $scheduled = [];

        $output = $this->prune([], $scheduled, failed: 1, expectedExit: 1);

        self::assertStringContainsString('found=2 deleted=0', $output);
        self::assertCount(2, $this->facade()->releasesForPackage('vendor/orphan', true, self::ORG));
    }

    #[Test]
    public function itSkipsAPackageCreatedWhileArchiveCleanupWasScheduled(): void
    {
        $this->seed();
        $scheduled = [];

        $output = $this->prune([], $scheduled, onSchedule: function(string $organizationId, string $packageName): void {
            $this->createPackage($packageName, $organizationId);
        });

        self::assertStringContainsString('found=2 deleted=0 skipped=2', $output);
        self::assertCount(2, $this->facade()->releasesForPackage('vendor/orphan', true, self::ORG));
    }

    #[Test]
    public function itKeepsReleasesAndContinuesWhenSchedulingThrows(): void
    {
        $this->seed();
        $this->publish('vendor/other-orphan', self::ORG);
        $scheduled = [];

        $output = $this->prune([], $scheduled, expectedExit: 1, onSchedule: static function(string $organizationId, string $packageName): void {
            if ('vendor/orphan' === $packageName) {
                throw new RuntimeException('Queue is down.');
            }
        });

        self::assertStringContainsString('found=3 deleted=1', $output);
        self::assertCount(2, $this->facade()->releasesForPackage('vendor/orphan', true, self::ORG));
        self::assertSame([], $this->facade()->releasesForPackage('vendor/other-orphan', true, self::ORG));
    }

    #[Test]
    public function itRemovesOnlyTheOrganizationWithoutThePackage(): void
    {
        $this->seed();
        $this->publish('vendor/kept', 'org-orphans-other');
        $scheduled = [];

        $this->prune([], $scheduled);

        self::assertContains(['org-orphans-other', 'vendor/kept'], $scheduled);
        self::assertNotContains([self::ORG, 'vendor/kept'], $scheduled);
        self::assertCount(1, $this->facade()->releasesForPackage('vendor/kept', true, self::ORG));
        self::assertSame([], $this->facade()->releasesForPackage('vendor/kept', true, 'org-orphans-other'));
    }

    /**
     * @param array<string, mixed>               $input
     * @param list<array{string, string}>        $scheduled
     * @param null|Closure(string, string): void $onSchedule
     */
    private function prune(
        array $input,
        array &$scheduled,
        int $failed = 0,
        int $expectedExit = 0,
        ?Closure $onSchedule = null
    ): string {
        $archive = new class($scheduled, $failed, $onSchedule) implements ArchiveStorageCleanupApiFacadeInterface {
            /**
             * @param list<array{string, string}> $scheduled
             */
            public function __construct(public array &$scheduled, private readonly int $failed, private readonly ?Closure $onSchedule) {}

            public function schedulePackageArchiveCleanup(
                string $organizationId,
                string $packageName,
                ?string $deletionRunId = null,
                string $origin = ''
            ): array {
                $this->scheduled[] = [$organizationId, $packageName];
                if ($this->onSchedule instanceof Closure) {
                    ($this->onSchedule)($organizationId, $packageName);
                }

                return [
                    'discovered' => 1,
                    'scheduled'  => 1 - $this->failed,
                    'failed'     => $this->failed,
                ];
            }
        };
        $repository = $this->getService(ReleaseRepositoryInterface::class);
        $packages   = $this->getService(PackageApiFacadeInterface::class);
        self::assertInstanceOf(ReleaseRepositoryInterface::class, $repository);
        self::assertInstanceOf(PackageApiFacadeInterface::class, $packages);

        $commandTester = new CommandTester(new ReleaseOrphansCleanupCommand($repository, $this->facade(), $packages, $archive));
        self::assertSame($expectedExit, $commandTester->execute($input));

        return $commandTester->getDisplay();
    }

    private function seed(): void
    {
        $facade = $this->facade();
        foreach ([['vendor/orphan', '1.0.0'], ['vendor/orphan', '2.0.0'], ['vendor/kept', '1.0.0']] as [$name, $version]) {
            $facade->publish(ReleaseBuilder::make([
                'packageName'    => $name,
                'version'        => $version,
                'organizationId' => self::ORG,
            ]));
        }

        $this->createPackage('vendor/kept', self::ORG);
    }

    private function publish(string $packageName, string $organizationId): void
    {
        $this->facade()->publish(ReleaseBuilder::make([
            'packageName'    => $packageName,
            'version'        => '1.0.0',
            'organizationId' => $organizationId,
        ]));
    }

    private function createPackage(string $packageName, string $organizationId): void
    {
        $packages = $this->getService(PackageApiFacadeInterface::class);
        self::assertInstanceOf(PackageApiFacadeInterface::class, $packages);
        $packages->upsert(new PackageDto(
            name: $packageName,
            type: 'library',
            description: '',
            visibility: 'public',
            sourceType: 'github',
            sourceUrl: '',
            createdAt: '',
            updatedAt: '',
            organizationId: $organizationId,
            sourceId: 'source-1',
        ));
    }

    private function facade(): ReleaseApiFacadeInterface
    {
        $facade = $this->getService(ReleaseApiFacadeInterface::class);
        self::assertInstanceOf(ReleaseApiFacadeInterface::class, $facade);

        return $facade;
    }
}
