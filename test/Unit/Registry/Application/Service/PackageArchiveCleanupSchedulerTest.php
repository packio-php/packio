<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Application\Service;

use Packio\Registry\Application\Port\JobSchedulerPort;
use Packio\Registry\Application\Port\ReleaseStorageMetadataPort;
use Packio\Registry\Application\Service\PackageArchiveCleanupScheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

use function array_map;
use function count;
use function range;

#[CoversClass(PackageArchiveCleanupScheduler::class)]
final class PackageArchiveCleanupSchedulerTest extends TestCase
{
    #[Test]
    public function itSchedulesUniquePackageArchiveKeysForAsyncCleanup(): void
    {
        $jobSchedulerPort = $this->createMock(JobSchedulerPort::class);
        $jobSchedulerPort->expects(self::once())->method('enqueue')->with(
            'org-1',
            'cleanup.package.archives',
            [
                'organization_id' => 'org-1',
                'package_name'    => 'vendor/package',
                'storage_keys'    => [
                    'private/org-1/dist/vendor/package/1.0.0_ref.zip',
                    'private/org-1/dist/vendor/package/1.1.0_ref.zip',
                ],
                'deletion_run_id' => 'run-1',
                'origin'          => 'organization.delete',
            ],
        );

        $result = $this->scheduler([
            'private/org-1/dist/vendor/package/1.0.0_ref.zip',
            'private/org-1/dist/vendor/package/1.1.0_ref.zip',
            'private/org-1/dist/vendor/package/1.1.0_ref.zip',
        ], $jobSchedulerPort)->schedule('org-1', 'vendor/package', 'run-1', 'organization.delete');

        self::assertSame([
            'discovered' => 2,
            'scheduled'  => 1,
            'failed'     => 0,
        ], $result);
    }

    #[Test]
    public function itSchedulesNewCleanupJobsForRepeatedArchiveKeys(): void
    {
        $jobSchedulerPort = $this->createMock(JobSchedulerPort::class);
        $jobSchedulerPort->expects(self::exactly(2))->method('enqueue')->with(
            'org-1',
            'cleanup.package.archives',
            [
                'organization_id' => 'org-1',
                'package_name'    => 'vendor/package',
                'storage_keys'    => ['private/org-1/dist/vendor/package/1.0.0_ref.zip'],
            ],
        );
        $packageArchiveCleanupScheduler = $this->scheduler(['private/org-1/dist/vendor/package/1.0.0_ref.zip'], $jobSchedulerPort);

        $packageArchiveCleanupScheduler->schedule('org-1', 'vendor/package');
        $packageArchiveCleanupScheduler->schedule('org-1', 'vendor/package');
    }

    #[Test]
    public function itChunksKeysByHundred(): void
    {
        $chunkSizes       = [];
        $jobSchedulerPort = $this->createMock(JobSchedulerPort::class);
        $jobSchedulerPort->expects(self::exactly(3))->method('enqueue')->willReturnCallback(
            static function(string $organizationId, string $type, array $payload) use (&$chunkSizes): void {
                $chunkSizes[] = count($payload['storage_keys']);
            },
        );

        $result = $this->scheduler($this->keys(205), $jobSchedulerPort)->schedule('org-1', 'vendor/package');

        self::assertSame([100, 100, 5], $chunkSizes);
        self::assertSame([
            'discovered' => 205,
            'scheduled'  => 3,
            'failed'     => 0,
        ], $result);
    }

    #[Test]
    public function itCountsFailedChunk(): void
    {
        $calls            = 0;
        $jobSchedulerPort = $this->createMock(JobSchedulerPort::class);
        $jobSchedulerPort->expects(self::exactly(2))->method('enqueue')->willReturnCallback(
            static function() use (&$calls): void {
                if (2 === ++$calls) {
                    throw new RuntimeException('Queue unavailable.');
                }
            },
        );

        $result = $this->scheduler($this->keys(150), $jobSchedulerPort)->schedule('org-1', 'vendor/package');

        self::assertSame([
            'discovered' => 150,
            'scheduled'  => 1,
            'failed'     => 1,
        ], $result);
    }

    #[Test]
    public function itReportsKeyLoadFailure(): void
    {
        $releaseStorageMetadataPort = $this->createStub(ReleaseStorageMetadataPort::class);
        $releaseStorageMetadataPort->method('distStorageKeysForPackage')->willThrowException(new RuntimeException('Database unavailable.'));
        $jobSchedulerPort = $this->createMock(JobSchedulerPort::class);
        $jobSchedulerPort->expects(self::never())->method('enqueue');

        $result = new PackageArchiveCleanupScheduler($releaseStorageMetadataPort, $jobSchedulerPort, new NullLogger())
            ->schedule('org-1', 'vendor/package')
        ;

        self::assertSame([
            'discovered' => 0,
            'scheduled'  => 0,
            'failed'     => 1,
        ], $result);
    }

    #[Test]
    public function itOmitsEmptyRunIdAndOrigin(): void
    {
        $jobSchedulerPort = $this->createMock(JobSchedulerPort::class);
        $jobSchedulerPort->expects(self::once())->method('enqueue')->with(
            'org-1',
            'cleanup.package.archives',
            [
                'organization_id' => 'org-1',
                'package_name'    => 'vendor/package',
                'storage_keys'    => ['key-1'],
            ],
        );

        $this->scheduler(['key-1'], $jobSchedulerPort)->schedule('org-1', 'vendor/package', '  ', '');
    }

    /**
     * @param list<string> $keys
     */
    private function scheduler(array $keys, JobSchedulerPort $jobSchedulerPort): PackageArchiveCleanupScheduler
    {
        $releaseStorageMetadataPort = $this->createStub(ReleaseStorageMetadataPort::class);
        $releaseStorageMetadataPort->method('distStorageKeysForPackage')->willReturn($keys);

        return new PackageArchiveCleanupScheduler($releaseStorageMetadataPort, $jobSchedulerPort, new NullLogger());
    }

    /**
     * @return list<string>
     */
    private function keys(int $count): array
    {
        return array_map(static fn (int $index): string => 'key-' . $index, range(1, $count));
    }
}
