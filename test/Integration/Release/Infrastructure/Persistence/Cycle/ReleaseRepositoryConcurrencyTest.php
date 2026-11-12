<?php

declare(strict_types=1);

namespace PackioTest\Integration\Release\Infrastructure\Persistence\Cycle;

use const JSON_THROW_ON_ERROR;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Infrastructure\Persistence\Cycle\ReleaseRepository;
use PackioTest\Support\Base\ForkedChildProcess;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;

use function bin2hex;
use function fclose;
use function fread;
use function function_exists;
use function fwrite;
use function json_decode;
use function pcntl_fork;
use function random_bytes;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[Group('slow')]
final class ReleaseRepositoryConcurrencyTest extends IntegrationTestCase
{
    use ForkedChildProcess;

    #[Test]
    public function concurrentPublishersLeaveOneReleaseAndDoNotExposeUniqueViolations(): void
    {
        $this->requireFork();

        $organizationId = 'org-' . bin2hex(random_bytes(6));
        $packageName    = 'vendor/package-' . bin2hex(random_bytes(6));
        $errorPath      = $this->newErrorPath();
        $this->disconnectDatabaseBeforeFork();

        [$firstParent, $firstChild]   = $this->socketPair();
        [$secondParent, $secondChild] = $this->socketPair();
        $firstPid                     = $this->forkPublisher($organizationId, $packageName, 'first-ref', $firstChild, $errorPath);
        $secondPid                    = $this->forkPublisher($organizationId, $packageName, 'second-ref', $secondChild, $errorPath);
        fclose($firstChild);
        fclose($secondChild);

        fwrite($firstParent, '1');
        fwrite($secondParent, '1');
        fclose($firstParent);
        fclose($secondParent);

        $this->assertChildExitedSuccessfully($firstPid, $errorPath);
        $this->assertChildExitedSuccessfully($secondPid, $errorPath);

        $releases = $this->repository()->findByPackageName($packageName, $organizationId);
        self::assertCount(1, $releases);
        self::assertContains($releases[0]->reference(), ['first-ref', 'second-ref']);

        @unlink($errorPath);
    }

    #[Test]
    public function publisherWithStaleOrmStatePreservesArchiveMetadataForTheSameReference(): void
    {
        $this->requireFork();

        $organizationId = 'org-' . bin2hex(random_bytes(6));
        $packageName    = 'vendor/package-' . bin2hex(random_bytes(6));
        $repository     = $this->repository();
        $repository->upsertByNaturalKey($this->candidate(
            $organizationId,
            $packageName,
            'same-ref',
            'original',
            'https://source.example/original.zip',
        ));

        $errorPath = $this->newErrorPath();
        $this->disconnectDatabaseBeforeFork();
        [$publishParent, $publishChild] = $this->socketPair();
        [$archiveParent, $archiveChild] = $this->socketPair();
        $publishPid                     = $this->forkPublisher(
            $organizationId,
            $packageName,
            'same-ref',
            $publishChild,
            $errorPath,
            'updated',
            'https://source.example/updated.zip',
        );
        $archivePid                     = $this->forkArchiveMetadataUpdate($organizationId, $packageName, $archiveChild, $errorPath);
        fclose($publishChild);
        fclose($archiveChild);

        fwrite($archiveParent, '1');
        fclose($archiveParent);
        $this->assertChildExitedSuccessfully($archivePid, $errorPath);

        fwrite($publishParent, '1');
        fclose($publishParent);
        $this->assertChildExitedSuccessfully($publishPid, $errorPath);

        $row          = $this->releaseRow($organizationId, $packageName);
        $composerData = json_decode((string) $row['composer_data'], true, 512, JSON_THROW_ON_ERROR);
        $distData     = json_decode((string) $row['dist_data'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($composerData);
        self::assertIsArray($distData);
        self::assertSame('updated', $composerData['description']);
        self::assertSame('https://source.example/updated.zip', $distData['url']);
        self::assertSame('same-ref', $distData['storage']['source_reference']);

        @unlink($errorPath);
    }

    #[Test]
    public function staleArchiveMetadataIsNotAttachedAfterTheReleaseReferenceChanges(): void
    {
        $this->requireFork();

        $organizationId = 'org-' . bin2hex(random_bytes(6));
        $packageName    = 'vendor/package-' . bin2hex(random_bytes(6));
        $this->repository()->upsertByNaturalKey($this->candidate(
            $organizationId,
            $packageName,
            'old-ref',
            'original',
            'https://source.example/old.zip',
        ));

        $errorPath = $this->newErrorPath();
        $this->disconnectDatabaseBeforeFork();
        [$publishParent, $publishChild] = $this->socketPair();
        $publishPid                     = $this->forkPublisher(
            $organizationId,
            $packageName,
            'new-ref',
            $publishChild,
            $errorPath,
            'updated',
            'https://source.example/new.zip',
        );
        fclose($publishChild);

        fwrite($publishParent, '1');
        fclose($publishParent);
        $this->assertChildExitedSuccessfully($publishPid, $errorPath);

        $updated = $this->repository()->updateDistStorageMetadataByNaturalKey(
            $organizationId,
            $packageName,
            '1.0.0',
            'old-ref',
            $this->storageData('old-ref'),
        );
        self::assertNull($updated);

        $row      = $this->releaseRow($organizationId, $packageName);
        $distData = json_decode((string) $row['dist_data'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($distData);
        self::assertSame('new-ref', $row['reference']);
        self::assertSame('https://source.example/new.zip', $distData['url']);
        self::assertArrayNotHasKey('storage', $distData);

        @unlink($errorPath);
    }

    private function requireFork(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }
    }

    private function repository(): ReleaseRepository
    {
        $repository = $this->getService(ReleaseRepository::class);
        if (! $repository instanceof ReleaseRepository) {
            throw new RuntimeException('ReleaseRepository is not configured.');
        }

        return $repository;
    }

    private function forkPublisher(
        string $organizationId,
        string $packageName,
        string $reference,
        mixed $startSocket,
        string $errorPath,
        string $description = 'published',
        string $distUrl = 'https://source.example/archive.zip',
    ): int {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);

        if (0 === $pid) {
            try {
                fread($startSocket, 1);
                fclose($startSocket);
                $this->repository()->upsertByNaturalKey($this->candidate(
                    $organizationId,
                    $packageName,
                    $reference,
                    $description,
                    $distUrl,
                ));

                exit(0);
            } catch (Throwable $throwable) {
                $this->recordChildError($errorPath, $throwable);

                exit(1);
            }
        }

        return $pid;
    }

    private function forkArchiveMetadataUpdate(string $organizationId, string $packageName, mixed $startSocket, string $errorPath): int
    {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);

        if (0 === $pid) {
            try {
                fread($startSocket, 1);
                fclose($startSocket);
                $release = $this->repository()->updateDistStorageMetadataByNaturalKey(
                    $organizationId,
                    $packageName,
                    '1.0.0',
                    'same-ref',
                    $this->storageData('same-ref'),
                );
                if (! $release instanceof Release) {
                    throw new RuntimeException('Release was not found for archive metadata update.');
                }

                exit(0);
            } catch (Throwable $throwable) {
                $this->recordChildError($errorPath, $throwable);

                exit(1);
            }
        }

        return $pid;
    }

    private function candidate(
        string $organizationId,
        string $packageName,
        string $reference,
        string $description,
        string $distUrl = 'https://source.example/archive.zip',
    ): Release {
        return Release::create(
            $organizationId,
            $packageName,
            '1.0.0',
            $reference,
            [
                'name'        => $packageName,
                'description' => $description,
            ],
            [
                'type' => 'git',
            ],
            [
                'type'      => 'zip',
                'url'       => $distUrl,
                'reference' => $reference,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseRow(string $organizationId, string $packageName): array
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $database = $databaseProvider->database();
        $rows     = $database
            ->getDriver(DatabaseInterface::WRITE)
            ->getQueryBuilder()
            ->selectQuery($database->getPrefix())
            ->from('releases')
            ->where('organization_id', $organizationId)
            ->where('package_name', $packageName)
            ->where('version', '1.0.0')
            ->fetchAll()
        ;
        self::assertCount(1, $rows);
        self::assertIsArray($rows[0]);

        return $rows[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function storageData(string $sourceReference): array
    {
        return [
            'driver'           => 'local',
            'bucket'           => null,
            'key'              => 'private/archive.zip',
            'content_type'     => 'application/zip',
            'size'             => 13,
            'sha256'           => 'abc123',
            'etag'             => null,
            'stored_at'        => '2026-07-16T12:00:00+00:00',
            'source_provider'  => 'github',
            'source_reference' => $sourceReference,
        ];
    }

    private function newErrorPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'packio-release-concurrency-errors-');
        self::assertIsString($path);

        return $path;
    }
}
