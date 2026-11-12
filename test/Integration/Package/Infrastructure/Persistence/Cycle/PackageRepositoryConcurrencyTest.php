<?php

declare(strict_types=1);

namespace PackioTest\Integration\Package\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Package\Domain\Entity\Package;
use Packio\Package\Domain\Exception\PackageSourceConflictException;
use Packio\Package\Infrastructure\Persistence\Cycle\PackageRepository;
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
use function pcntl_fork;
use function random_bytes;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[Group('slow')]
final class PackageRepositoryConcurrencyTest extends IntegrationTestCase
{
    use ForkedChildProcess;

    #[Test]
    public function staleUnownedPackageCannotBeClaimedByASecondSource(): void
    {
        $this->requireFork();

        $organizationId = 'org-' . bin2hex(random_bytes(6));
        $packageName    = 'vendor/package-' . bin2hex(random_bytes(6));
        $this->repository()->upsertByNaturalKey($this->candidate($organizationId, $packageName, '', 'unowned'));

        $errorPath = $this->newErrorPath();
        $this->disconnectDatabaseBeforeFork();
        [$firstParent, $firstChild]   = $this->socketPair();
        [$secondParent, $secondChild] = $this->socketPair();
        $firstPid                     = $this->forkPublisher(
            $organizationId,
            $packageName,
            'source-a',
            $firstChild,
            $errorPath,
            false,
        );
        $secondPid = $this->forkPublisher(
            $organizationId,
            $packageName,
            'source-b',
            $secondChild,
            $errorPath,
            true,
        );
        fclose($firstChild);
        fclose($secondChild);

        fwrite($firstParent, '1');
        fclose($firstParent);
        $this->assertChildExitedSuccessfully($firstPid, $errorPath);

        fwrite($secondParent, '1');
        fclose($secondParent);
        $this->assertChildExitedSuccessfully($secondPid, $errorPath);

        $row = $this->packageRow($organizationId, $packageName);
        self::assertSame('source-a', $row['source_id']);
        self::assertSame('published-source-a', $row['description']);

        @unlink($errorPath);
    }

    private function requireFork(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }
    }

    private function repository(): PackageRepository
    {
        $repository = $this->getService(PackageRepository::class);
        if (! $repository instanceof PackageRepository) {
            throw new RuntimeException('PackageRepository is not configured.');
        }

        return $repository;
    }

    private function forkPublisher(
        string $organizationId,
        string $packageName,
        string $sourceId,
        mixed $startSocket,
        string $errorPath,
        bool $expectConflict,
    ): int {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);

        if (0 === $pid) {
            try {
                fread($startSocket, 1);
                fclose($startSocket);

                try {
                    $this->repository()->upsertByNaturalKey($this->candidate(
                        $organizationId,
                        $packageName,
                        $sourceId,
                        'published-' . $sourceId,
                    ));
                } catch (PackageSourceConflictException $exception) {
                    if ($expectConflict) {
                        exit(0);
                    }

                    throw $exception;
                }

                if ($expectConflict) {
                    throw new RuntimeException('Expected package source ownership conflict was not raised.');
                }

                exit(0);
            } catch (Throwable $throwable) {
                $this->recordChildError($errorPath, $throwable);

                exit(1);
            }
        }

        return $pid;
    }

    private function candidate(string $organizationId, string $packageName, string $sourceId, string $description): Package
    {
        return Package::create(
            organizationId: $organizationId,
            name: $packageName,
            type: 'library',
            description: $description,
            visibility: 'public',
            sourceId: $sourceId,
            sourceType: 'github',
            sourceUrl: 'https://github.example/' . $packageName,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function packageRow(string $organizationId, string $packageName): array
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $database = $databaseProvider->database();
        $rows     = $database
            ->getDriver(DatabaseInterface::WRITE)
            ->getQueryBuilder()
            ->selectQuery($database->getPrefix())
            ->from('packages')
            ->where('organization_id', $organizationId)
            ->where('name', $packageName)
            ->fetchAll()
        ;
        self::assertCount(1, $rows);
        self::assertIsArray($rows[0]);

        return $rows[0];
    }

    private function newErrorPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'packio-package-concurrency-errors-');
        self::assertIsString($path);

        return $path;
    }
}
