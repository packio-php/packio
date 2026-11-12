<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Infrastructure\Persistence\Cycle;

use const STREAM_IPPROTO_IP;
use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Worker\Application\JobQueueService;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\ForkedChildProcess;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;

use function bin2hex;
use function fclose;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fread;
use function function_exists;
use function fwrite;
use function pcntl_fork;
use function pcntl_waitpid;
use function pcntl_wexitstatus;
use function pcntl_wifexited;
use function random_bytes;
use function sort;
use function sprintf;
use function stream_socket_pair;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function usleep;

#[Group('slow')]
final class OrganizationDeletionInitiationConcurrencyTest extends IntegrationTestCase
{
    use ForkedChildProcess;

    #[Test]
    public function concurrentInitiationsProduceOneInitiatedOutcomeAndOneJob(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'org-initiation-' . bin2hex(random_bytes(6)),
        );

        $this->disconnectDatabaseBeforeFork();
        $errorPath = tempnam(sys_get_temp_dir(), 'packio-org-initiation-errors-');
        self::assertIsString($errorPath);

        try {
            $statuses = $this->initiateDeletionConcurrently($organizationDto, $errorPath);
            sort($statuses);

            self::assertSame([0, 2], $statuses, $this->childErrors($errorPath));

            /** @var JobRepositoryInterface $jobs */
            $jobs = $this->getService(JobRepositoryInterface::class);
            self::assertCount(1, $jobs->findByOrganization($organizationDto->id, [
                'type' => 'organization.delete',
            ]));

            self::assertSame('deleting', $this->rawOrganizationStatus($organizationDto->id));
        } finally {
            @unlink($errorPath);
        }
    }

    #[Test]
    public function aFailingInitiatorCannotRevertAGenerationRecoveredByAnotherProducer(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $jobRepository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobRepository);

        $claimedPath = tempnam(sys_get_temp_dir(), 'packio-org-enqueue-claimed-');
        $releasePath = tempnam(sys_get_temp_dir(), 'packio-org-enqueue-release-');
        self::assertIsString($claimedPath);
        self::assertIsString($releasePath);
        @unlink($claimedPath);
        @unlink($releasePath);

        $this->serviceOverrides[JobQueueService::class] = new class($jobRepository, $claimedPath, $releasePath) extends JobQueueService {
            public function __construct(
                JobRepositoryInterface $jobRepository,
                private readonly string $claimedPath,
                private readonly string $releasePath,
            ) {
                parent::__construct($jobRepository);
            }

            public function enqueueOnceHeld(
                string $organizationId,
                string $type,
                string $idempotencyKey,
                array $payload,
                int $priority = 0,
                ?Chronos $scheduledAt = null,
                int $maxAttempts = 3,
            ): Job {
                if (! file_exists($this->claimedPath)) {
                    file_put_contents($this->claimedPath, '1', LOCK_EX);

                    while (! file_exists($this->releasePath)) {
                        usleep(1_000);
                    }

                    throw new RuntimeException('Injected delayed enqueue failure.');
                }

                return parent::enqueueOnceHeld(
                    $organizationId,
                    $type,
                    $idempotencyKey,
                    $payload,
                    $priority,
                    $scheduledAt,
                    $maxAttempts,
                );
            }
        };
        $this->rebuildContainer();

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $organizationDto = $organizationApiFacade->registerOrganization(
            'org-enqueue-race-' . bin2hex(random_bytes(6)),
        );

        $this->disconnectDatabaseBeforeFork();
        $initiatorPid = pcntl_fork();
        self::assertNotFalse($initiatorPid);

        if (0 === $initiatorPid) {
            try {
                $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
                if (! $organizationApiFacade instanceof OrganizationApiFacadeInterface) {
                    throw new RuntimeException('OrganizationApiFacadeInterface is not configured.');
                }

                $organizationApiFacade->deleteOrganizationWithOutcome($organizationDto->id);

                exit(1);
            } catch (RuntimeException $exception) {
                exit('Injected delayed enqueue failure.' === $exception->getMessage() ? 0 : 1);
            }
        }

        try {
            for ($attempt = 0; $attempt < 1_000 && ! file_exists($claimedPath); ++$attempt) {
                usleep(1_000);
            }
            self::assertFileExists($claimedPath);

            $recoveryPid = pcntl_fork();
            self::assertNotFalse($recoveryPid);

            if (0 === $recoveryPid) {
                try {
                    $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
                    if (! $organizationApiFacade instanceof OrganizationApiFacadeInterface) {
                        throw new RuntimeException('OrganizationApiFacadeInterface is not configured.');
                    }

                    $result = $organizationApiFacade->deleteOrganizationWithOutcome($organizationDto->id);

                    exit(OrganizationDeletionInitiationOutcome::AlreadyDeleting === $result->outcome ? 0 : 1);
                } catch (Throwable) {
                    exit(1);
                }
            }

            $status = 0;
            pcntl_waitpid($recoveryPid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));

            file_put_contents($releasePath, '1', LOCK_EX);
            $status = 0;
            pcntl_waitpid($initiatorPid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));

            $this->rebuildContainer();
            self::assertSame('deleting', $this->rawOrganizationStatus($organizationDto->id));

            $jobs = $this->getService(JobRepositoryInterface::class);
            self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
            $organizationJobs = $jobs->findByOrganization($organizationDto->id, [
                'type' => 'organization.delete',
            ]);
            self::assertCount(1, $organizationJobs);
            self::assertSame(Job::STATUS_HELD, $organizationJobs[0]->status());
        } finally {
            file_put_contents($releasePath, '1', LOCK_EX);
            unset($this->serviceOverrides[JobQueueService::class]);
            $this->rebuildContainer();
            @unlink($claimedPath);
            @unlink($releasePath);
        }
    }

    /**
     * @return list<int>
     */
    private function initiateDeletionConcurrently(OrganizationDto $organizationDto, string $errorPath): array
    {
        $children = [];

        foreach ([1, 2] as $worker) {
            $releaseSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertIsArray($releaseSockets);
            $readySockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertIsArray($readySockets);

            $pid = pcntl_fork();
            self::assertNotFalse($pid);

            if (0 === $pid) {
                fclose($releaseSockets[0]);
                fclose($readySockets[0]);
                fwrite($readySockets[1], '1');
                fclose($readySockets[1]);
                @fread($releaseSockets[1], 1);
                fclose($releaseSockets[1]);

                $this->initiateDeletionInChild($organizationDto->id, $errorPath);
            }

            fclose($releaseSockets[1]);
            fclose($readySockets[1]);
            $children[] = [
                'pid'           => $pid,
                'readySocket'   => $readySockets[0],
                'releaseSocket' => $releaseSockets[0],
            ];
        }

        foreach ($children as $child) {
            self::assertSame('1', fread($child['readySocket'], 1));
            fclose($child['readySocket']);
        }

        foreach ($children as $child) {
            fwrite($child['releaseSocket'], '1');
            fclose($child['releaseSocket']);
        }

        $statuses = [];
        foreach ($children as $child) {
            $status = 0;
            pcntl_waitpid($child['pid'], $status);
            $exitStatus = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : false;
            $statuses[] = false === $exitStatus ? 1 : $exitStatus;
        }

        return $statuses;
    }

    private function initiateDeletionInChild(string $organizationId, string $errorPath): never
    {
        try {
            $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
            if (! $organizationApiFacade instanceof OrganizationApiFacadeInterface) {
                throw new RuntimeException('OrganizationApiFacadeInterface is not configured.');
            }

            exit(match ($organizationApiFacade->deleteOrganizationWithOutcome($organizationId)->outcome) {
                OrganizationDeletionInitiationOutcome::Initiated       => 0,
                OrganizationDeletionInitiationOutcome::AlreadyDeleting => 2,
                OrganizationDeletionInitiationOutcome::NotFound        => 3,
            });
        } catch (Throwable $throwable) {
            file_put_contents(
                $errorPath,
                sprintf("%s: %s\n", $throwable::class, $throwable->getMessage()),
                FILE_APPEND,
            );

            exit(1);
        }
    }

    /**
     * Reads the status straight from PostgreSQL: the forked children applied
     * their updates through raw conditional SQL, so the parent ORM heap may
     * still cache the pre-initiation entity.
     */
    private function rawOrganizationStatus(string $organizationId): string
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        $rows = $databaseProvider->database()->select('status')
            ->from('organizations')
            ->where('uuid', $organizationId)
            ->fetchAll()
        ;

        self::assertCount(1, $rows);

        return (string) ($rows[0]['status'] ?? '');
    }

    private function childErrors(string $errorPath): string
    {
        return (string) file_get_contents($errorPath);
    }
}
