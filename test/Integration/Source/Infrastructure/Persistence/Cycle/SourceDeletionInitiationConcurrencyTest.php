<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Persistence\Cycle;

use const STREAM_IPPROTO_IP;
use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryException;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryService;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Audit\Api\Dto\AuditEntryDto;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Dto\SourceDeleteArtifactsResult;
use Packio\Source\Application\Exception\SourceDeletionExecutionLeaseLostException;
use Packio\Source\Application\Port\SourceDeleteArtifactsPort;
use Packio\Source\Application\Service\SourceDeletionExecutionLeaseInterface;
use Packio\Worker\Api\Exception\WorkerRestartRequiredException;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Application\JobQueueService;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use Packio\Worker\Infrastructure\Cli\WorkerRunCommand;
use PackioTest\Support\Base\ForkedChildProcess;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

use function array_filter;
use function bin2hex;
use function count;
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
final class SourceDeletionInitiationConcurrencyTest extends IntegrationTestCase
{
    use ForkedChildProcess;

    private const string ADMIN_USER_ID = 'admin-concurrency-user';

    #[Test]
    public function concurrentInitiationsProduceOneInitiatedOutcomeOneJobAndOneAuditEntry(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);

        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-initiation-' . bin2hex(random_bytes(6)),
        );
        $sourceDto       = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-initiation-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));

        $this->disconnectDatabaseBeforeFork();
        $errorPath = tempnam(sys_get_temp_dir(), 'packio-source-initiation-errors-');
        self::assertIsString($errorPath);

        try {
            $statuses = $this->initiateDeletionConcurrently($sourceDto, $errorPath);
            sort($statuses);

            // One producer initiates and the other resumes the same durable
            // lifecycle; both complete successfully without a duplicate job.
            self::assertSame([0, 0], $statuses, $this->childErrors($errorPath));

            /** @var JobRepositoryInterface $jobs */
            $jobs = $this->getService(JobRepositoryInterface::class);
            self::assertCount(1, $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id));

            self::assertSame('deleting', $this->rawSourceStatus($sourceDto->id));

            $initiationEntries = array_filter(
                $this->auditEntries($organizationDto, $sourceDto),
                static fn (AuditEntryDto $auditEntryDto): bool => 'source.delete.initiated_by_admin' === $auditEntryDto->event,
            );
            self::assertCount(1, $initiationEntries);
        } finally {
            @unlink($errorPath);
        }
    }

    #[Test]
    public function sequentialRearmProducersReuseTheSameNewDeletionLifecycleJobAfterATerminalJob(): void
    {
        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);

        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-rearm-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-rearm-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));

        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $terminalJob = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id)[0] ?? null;
        self::assertNotNull($terminalJob);
        $terminalJob->markCanceled();
        $jobs->save($terminalJob);

        self::assertNotNull($sourceApiFacade->cancelDeletingAsInactive($organizationDto->id, $sourceDto->id));

        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));
        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $sourceDeleteJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(2, $sourceDeleteJobs);
        self::assertCount(
            1,
            array_filter($sourceDeleteJobs, static fn ($job): bool => 'pending' === $job->status()),
        );
    }

    #[Test]
    public function aFailingInitiatorCannotRevertAGenerationRecoveredByAnotherProducer(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $jobRepository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobRepository);

        $claimedPath = tempnam(sys_get_temp_dir(), 'packio-source-enqueue-claimed-');
        $releasePath = tempnam(sys_get_temp_dir(), 'packio-source-enqueue-release-');
        $errorPath   = tempnam(sys_get_temp_dir(), 'packio-source-enqueue-errors-');
        self::assertIsString($claimedPath);
        self::assertIsString($releasePath);
        self::assertIsString($errorPath);
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
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-enqueue-race-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-enqueue-race-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));

        $this->disconnectDatabaseBeforeFork();
        $initiatorPid = pcntl_fork();
        self::assertNotFalse($initiatorPid);

        if (0 === $initiatorPid) {
            try {
                $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
                if (! $sourceApiFacade instanceof SourceApiFacadeInterface) {
                    throw new RuntimeException('SourceApiFacadeInterface is not configured.');
                }

                $sourceApiFacade->initiateSourceDelete($organizationDto->id, $sourceDto->id);

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
                    $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
                    if (! $sourceApiFacade instanceof SourceApiFacadeInterface) {
                        throw new RuntimeException('SourceApiFacadeInterface is not configured.');
                    }

                    $result    = $sourceApiFacade->initiateSourceDelete($organizationDto->id, $sourceDto->id);
                    $scheduled = $sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id);
                    if (! $scheduled) {
                        file_put_contents($errorPath, sprintf(
                            'Recovery producer returned false (outcome=%s, job=%s).',
                            $result->outcome->value,
                            $result->jobId ?? 'none',
                        ));
                    }

                    exit($scheduled ? 0 : 1);
                } catch (Throwable $throwable) {
                    file_put_contents($errorPath, $throwable::class . ': ' . $throwable->getMessage());

                    exit(1);
                }
            }

            $status = 0;
            pcntl_waitpid($recoveryPid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status), (string) file_get_contents($errorPath));

            file_put_contents($releasePath, '1', LOCK_EX);
            $status = 0;
            pcntl_waitpid($initiatorPid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));

            $this->rebuildContainer();
            self::assertSame('deleting', $this->rawSourceStatus($sourceDto->id));

            $jobs = $this->getService(JobRepositoryInterface::class);
            self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
            $sourceDeleteJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
            self::assertCount(1, $sourceDeleteJobs);
            self::assertSame(Job::STATUS_PENDING, $sourceDeleteJobs[0]->status());
        } finally {
            file_put_contents($releasePath, '1', LOCK_EX);
            unset($this->serviceOverrides[JobQueueService::class]);
            $this->rebuildContainer();
            @unlink($claimedPath);
            @unlink($releasePath);
            @unlink($errorPath);
        }
    }

    #[Test]
    public function forceRequeueCannotReplaceTheGenerationAfterAWorkerPassedItsExecutionGuard(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $realSourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $realSourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-force-requeue-race-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $realSourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-force-requeue-race-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));
        self::assertTrue($realSourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $adminReadyPath   = tempnam(sys_get_temp_dir(), 'packio-source-force-admin-ready-');
        $workerInsidePath = tempnam(sys_get_temp_dir(), 'packio-source-force-worker-inside-');
        $rearmDonePath    = tempnam(sys_get_temp_dir(), 'packio-source-force-rearm-done-');
        $errorPath        = tempnam(sys_get_temp_dir(), 'packio-source-force-error-');
        self::assertIsString($adminReadyPath);
        self::assertIsString($workerInsidePath);
        self::assertIsString($rearmDonePath);
        self::assertIsString($errorPath);
        @unlink($adminReadyPath);
        @unlink($workerInsidePath);
        @unlink($rearmDonePath);
        @unlink($errorPath);

        $this->serviceOverrides[SourceDeleteArtifactsPort::class] = new readonly class($workerInsidePath, $rearmDonePath) implements SourceDeleteArtifactsPort {
            public function __construct(
                private string $workerInsidePath,
                private string $rearmDonePath,
            ) {}

            public function deleteRelatedArtifacts(
                string $sourceId,
                string $organizationId,
                ?string $deletionRunId = null,
                string $origin = '',
            ): SourceDeleteArtifactsResult {
                file_put_contents($this->workerInsidePath, '1', LOCK_EX);
                while (! file_exists($this->rearmDonePath)) {
                    usleep(1_000);
                }

                return new SourceDeleteArtifactsResult(0, 0, 0, 0, 0, 0);
            }
        };
        $this->rebuildContainer();

        /** @var JobRepositoryInterface $jobs */
        $jobs      = $this->getService(JobRepositoryInterface::class);
        $deleteJob = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id)[0] ?? null;
        self::assertInstanceOf(Job::class, $deleteJob);

        $this->disconnectDatabaseBeforeFork();
        $adminPid = pcntl_fork();
        self::assertNotFalse($adminPid);
        if (0 === $adminPid) {
            try {
                file_put_contents($adminReadyPath, '1', LOCK_EX);
                while (! file_exists($workerInsidePath)) {
                    usleep(1_000);
                }

                $sourceCommandService = $this->getService(SourceCommandService::class);
                if (! $sourceCommandService instanceof SourceCommandService) {
                    throw new RuntimeException('SourceCommandService is not configured.');
                }

                $deletionRunId = $sourceCommandService->recoverDeletionRun(
                    $sourceDto->id,
                    $organizationDto->id,
                    true,
                );
                file_put_contents($rearmDonePath, '1', LOCK_EX);

                exit(null === $deletionRunId ? 0 : 2);
            } catch (Throwable $throwable) {
                file_put_contents($errorPath, $throwable::class . ': ' . $throwable->getMessage(), LOCK_EX);
                file_put_contents($rearmDonePath, '1', LOCK_EX);

                exit(1);
            }
        }

        try {
            for ($attempt = 0; $attempt < 10_000 && ! file_exists($adminReadyPath) && ! file_exists($errorPath); ++$attempt) {
                usleep(1_000);
            }
            self::assertFileExists(
                $adminReadyPath,
                file_exists($errorPath) ? (string) file_get_contents($errorPath) : '',
            );

            $jobProcessor = $this->getService(JobProcessor::class);
            self::assertInstanceOf(JobProcessor::class, $jobProcessor);
            $jobProcessor->process($deleteJob);

            $status = 0;
            pcntl_waitpid($adminPid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));

            $this->rebuildContainer();
            $jobs = $this->getService(JobRepositoryInterface::class);
            self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
            self::assertCount(1, $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id));
        } finally {
            file_put_contents($rearmDonePath, '1', LOCK_EX);
            unset(
                $this->serviceOverrides[SourceDeleteArtifactsPort::class],
            );
            $this->rebuildContainer();
            @unlink($adminReadyPath);
            @unlink($workerInsidePath);
            @unlink($rearmDonePath);
            @unlink($errorPath);
        }
    }

    #[Test]
    public function aCrashedWorkerConnectionReleasesTheExecutionLeaseForRecovery(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-execution-crash-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-execution-crash-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));
        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $deleteJob = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id)[0] ?? null;
        self::assertInstanceOf(Job::class, $deleteJob);
        $deletionRunId = $deleteJob->payload()['deletion_run_id'] ?? null;
        self::assertIsString($deletionRunId);

        $this->disconnectDatabaseBeforeFork();
        $workerPid = pcntl_fork();
        self::assertNotFalse($workerPid);
        if (0 === $workerPid) {
            $executionLease = $this->getService(SourceDeletionExecutionLeaseInterface::class);

            exit($executionLease instanceof SourceDeletionExecutionLeaseInterface
                && $executionLease->acquire(
                    $sourceDto->id,
                    $organizationDto->id,
                    $deletionRunId,
                ) ? 0 : 1);
        }

        $status = 0;
        pcntl_waitpid($workerPid, $status);
        self::assertTrue(pcntl_wifexited($status));
        self::assertSame(0, pcntl_wexitstatus($status));

        $this->rebuildContainer();
        $sourceCommandService = $this->getService(SourceCommandService::class);
        self::assertInstanceOf(SourceCommandService::class, $sourceCommandService);
        self::assertNotNull($sourceCommandService->recoverDeletionRun(
            $sourceDto->id,
            $organizationDto->id,
            true,
        ));
    }

    #[Test]
    public function aLiveWorkerCannotContinueAfterItsExecutionLeaseConnectionIsTerminated(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-lease-disconnect-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-lease-disconnect-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));
        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $deleteJob = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id)[0] ?? null;
        self::assertInstanceOf(Job::class, $deleteJob);
        $deletionRunId = $deleteJob->payload()['deletion_run_id'] ?? null;
        self::assertIsString($deletionRunId);

        $backendPidPath = tempnam(sys_get_temp_dir(), 'packio-source-lease-pid-');
        $releasePath    = tempnam(sys_get_temp_dir(), 'packio-source-lease-release-');
        self::assertIsString($backendPidPath);
        self::assertIsString($releasePath);
        @unlink($backendPidPath);
        @unlink($releasePath);

        $this->disconnectDatabaseBeforeFork();
        $workerPid = pcntl_fork();
        self::assertNotFalse($workerPid);
        if (0 === $workerPid) {
            $executionLease   = $this->getService(SourceDeletionExecutionLeaseInterface::class);
            $databaseProvider = $this->getService('dbal');
            if (
                ! $executionLease instanceof SourceDeletionExecutionLeaseInterface
                || ! $databaseProvider instanceof DatabaseProviderInterface
                || ! $executionLease->acquire(
                    $sourceDto->id,
                    $organizationDto->id,
                    $deletionRunId,
                )
            ) {
                exit(1);
            }

            $backendPid = $databaseProvider->database('source_deletion_lease')
                ->getDriver(DatabaseInterface::WRITE)
                ->query('SELECT pg_backend_pid()')
                ->fetchColumn()
            ;
            file_put_contents($backendPidPath, (string) $backendPid, LOCK_EX);
            while (! file_exists($releasePath)) {
                usleep(1_000);
            }

            try {
                $executionLease->assertHeld($sourceDto->id, $organizationDto->id, $deletionRunId);
            } catch (SourceDeletionExecutionLeaseLostException) {
                exit(0);
            }

            exit(2);
        }

        try {
            for ($attempt = 0; $attempt < 10_000 && ! file_exists($backendPidPath); ++$attempt) {
                usleep(1_000);
            }
            self::assertFileExists($backendPidPath);
            $backendPid = (int) file_get_contents($backendPidPath);
            self::assertGreaterThan(0, $backendPid);

            $databaseProvider = $this->getService('dbal');
            self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
            self::assertTrue((bool) $databaseProvider->database()
                ->getDriver(DatabaseInterface::WRITE)
                ->query('SELECT pg_terminate_backend(?)', [$backendPid])
                ->fetchColumn());

            file_put_contents($releasePath, '1', LOCK_EX);
            $status = 0;
            pcntl_waitpid($workerPid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));

            $recovery = $sourceApiFacade->scheduleSourceDeletionRecovery(
                $organizationDto->id,
                $sourceDto->id,
                true,
                'lease-disconnect-reclaim',
            );
            self::assertNotNull($recovery);
            self::assertNotSame($deletionRunId, $recovery->deletionRunId);
        } finally {
            file_put_contents($releasePath, '1', LOCK_EX);
            @unlink($backendPidPath);
            @unlink($releasePath);
        }
    }

    #[Test]
    public function workerExitsForSupervisorRestartWhenAnIdleLeaseConnectionDiesBeforeTheNextAcquire(): void
    {
        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-idle-lease-disconnect-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-idle-lease-disconnect-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));
        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $deleteJob = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id)[0] ?? null;
        self::assertInstanceOf(Job::class, $deleteJob);
        $deletionRunId = $deleteJob->payload()['deletion_run_id'] ?? null;
        self::assertIsString($deletionRunId);

        $executionLease = $this->getService(SourceDeletionExecutionLeaseInterface::class);
        self::assertInstanceOf(SourceDeletionExecutionLeaseInterface::class, $executionLease);
        self::assertTrue($executionLease->acquire(
            $sourceDto->id,
            $organizationDto->id,
            $deletionRunId,
        ));
        $executionLease->rollback($sourceDto->id, $organizationDto->id, $deletionRunId);

        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $leaseDriver = $databaseProvider->database('source_deletion_lease')
            ->getDriver(DatabaseInterface::WRITE)
        ;
        $backendPid = $leaseDriver->query('SELECT pg_backend_pid()')->fetchColumn();
        self::assertIsInt($backendPid);
        self::assertTrue((bool) $databaseProvider->database()
            ->getDriver(DatabaseInterface::WRITE)
            ->query('SELECT pg_terminate_backend(?)', [$backendPid])
            ->fetchColumn());

        $workerJobRepository = $this->createMock(JobRepositoryInterface::class);
        $workerJobRepository->expects(self::once())->method('findStuck')->with(30)->willReturn([]);
        $workerJobRepository->expects(self::once())
            ->method('dequeue')
            ->with('idle-lease-regression-worker', 0)
            ->willReturn($deleteJob)
        ;
        $jobProcessor = $this->getService(JobProcessor::class);
        self::assertInstanceOf(JobProcessor::class, $jobProcessor);
        $workerRunCommand = new WorkerRunCommand($workerJobRepository, $jobProcessor);
        $bufferedOutput   = new BufferedOutput();
        $exitCode         = $workerRunCommand->run(new ArrayInput([
            '--timeout'   => '0',
            '--max-jobs'  => '1',
            '--worker-id' => 'idle-lease-regression-worker',
        ]), $bufferedOutput);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('must restart', $bufferedOutput->fetch());

        $this->rebuildContainer();
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $persistedJob = $jobs->findById($deleteJob->getIdentifier()->toString());
        self::assertInstanceOf(Job::class, $persistedJob);
        self::assertSame(Job::STATUS_PENDING, $persistedJob->status());
    }

    #[Test]
    public function forceRearmCannotPassAWorkerPausedAfterItsSuccessfulCheckpointBeforeMutation(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-atomic-fence-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-atomic-fence-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));
        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $deleteJob = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id)[0] ?? null;
        self::assertInstanceOf(Job::class, $deleteJob);
        $deletionRunId = $deleteJob->payload()['deletion_run_id'] ?? null;
        self::assertIsString($deletionRunId);

        $backendPidPath   = tempnam(sys_get_temp_dir(), 'packio-source-atomic-pid-');
        $checkpointPath   = tempnam(sys_get_temp_dir(), 'packio-source-atomic-checkpoint-');
        $releasePath      = tempnam(sys_get_temp_dir(), 'packio-source-atomic-release-');
        $rearmStartedPath = tempnam(sys_get_temp_dir(), 'packio-source-atomic-rearm-started-');
        $rearmDonePath    = tempnam(sys_get_temp_dir(), 'packio-source-atomic-rearm-done-');
        self::assertIsString($backendPidPath);
        self::assertIsString($checkpointPath);
        self::assertIsString($releasePath);
        self::assertIsString($rearmStartedPath);
        self::assertIsString($rearmDonePath);
        @unlink($backendPidPath);
        @unlink($checkpointPath);
        @unlink($releasePath);
        @unlink($rearmStartedPath);
        @unlink($rearmDonePath);

        $sourceDeleteArtifactsPort = new class($backendPidPath, $checkpointPath, $releasePath) implements SourceDeleteArtifactsPort {
            private ?DatabaseProviderInterface $databaseProvider = null;

            public function __construct(
                private readonly string $backendPidPath,
                private readonly string $checkpointPath,
                private readonly string $releasePath,
            ) {}

            public function useDatabaseProvider(DatabaseProviderInterface $databaseProvider): void
            {
                $this->databaseProvider = $databaseProvider;
            }

            public function deleteRelatedArtifacts(
                string $sourceId,
                string $organizationId,
                ?string $deletionRunId = null,
                string $origin = '',
            ): SourceDeleteArtifactsResult {
                $databaseProvider = $this->databaseProvider;
                if (! $databaseProvider instanceof DatabaseProviderInterface) {
                    throw new RuntimeException('Database provider was not configured for the blocking cleanup adapter.');
                }

                $backendPid = $databaseProvider->database('source_deletion_lease')
                    ->getDriver(DatabaseInterface::WRITE)
                    ->query('SELECT pg_backend_pid()')
                    ->fetchColumn()
                ;
                file_put_contents($this->backendPidPath, (string) $backendPid, LOCK_EX);
                file_put_contents($this->checkpointPath, '1', LOCK_EX);
                while (! file_exists($this->releasePath)) {
                    usleep(1_000);
                }

                $databaseProvider->database()
                    ->delete('sources', [
                        'uuid'            => $sourceId,
                        'organization_id' => $organizationId,
                    ])
                    ->run()
                ;

                return new SourceDeleteArtifactsResult(0, 0, 0, 0, 0, 0);
            }
        };
        $this->serviceOverrides[SourceDeleteArtifactsPort::class] = $sourceDeleteArtifactsPort;
        $this->rebuildContainer();
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $sourceDeleteArtifactsPort->useDatabaseProvider($databaseProvider);

        $this->disconnectDatabaseBeforeFork();
        $workerPid = pcntl_fork();
        self::assertNotFalse($workerPid);
        if (0 === $workerPid) {
            try {
                $jobProcessor  = $this->getService(JobProcessor::class);
                $jobRepository = $this->getService(JobRepositoryInterface::class);
                $childJob      = $jobRepository instanceof JobRepositoryInterface
                    ? $jobRepository->findById($deleteJob->getIdentifier()->toString())
                    : null;
                if (! $jobProcessor instanceof JobProcessor || ! $childJob instanceof Job) {
                    exit(1);
                }

                $jobProcessor->process($childJob);
            } catch (WorkerRestartRequiredException) {
                // Expected after the parent terminates the exact lease session.
            }

            exit(0);
        }

        $rearmPid = null;

        try {
            for ($attempt = 0; $attempt < 10_000 && ! file_exists($checkpointPath); ++$attempt) {
                usleep(1_000);
            }
            self::assertFileExists($checkpointPath);
            self::assertFileExists($backendPidPath);
            $backendPid = (int) file_get_contents($backendPidPath);
            self::assertGreaterThan(0, $backendPid);

            $databaseProvider = $this->getService('dbal');
            self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
            self::assertTrue((bool) $databaseProvider->database()
                ->getDriver(DatabaseInterface::WRITE)
                ->query('SELECT pg_terminate_backend(?)', [$backendPid])
                ->fetchColumn());

            $this->disconnectDatabaseBeforeFork();
            $rearmPid = pcntl_fork();
            self::assertNotFalse($rearmPid);
            if (0 === $rearmPid) {
                file_put_contents($rearmStartedPath, '1', LOCK_EX);
                $sourceCommandService = $this->getService(SourceCommandService::class);
                if (! $sourceCommandService instanceof SourceCommandService) {
                    exit(1);
                }

                $rearmedRunId = $sourceCommandService->recoverDeletionRun(
                    $sourceDto->id,
                    $organizationDto->id,
                    true,
                );
                file_put_contents($rearmDonePath, (string) $rearmedRunId, LOCK_EX);

                exit(null === $rearmedRunId ? 2 : 0);
            }

            for ($attempt = 0; $attempt < 10_000 && ! file_exists($rearmStartedPath); ++$attempt) {
                usleep(1_000);
            }
            self::assertFileExists($rearmStartedPath);

            for ($attempt = 0; $attempt < 1_000 && ! file_exists($rearmDonePath); ++$attempt) {
                usleep(1_000);
            }
            self::assertFileDoesNotExist(
                $rearmDonePath,
                'Force re-arm crossed the worker mutation boundary after the lease checkpoint became stale.',
            );
        } finally {
            file_put_contents($releasePath, '1', LOCK_EX);

            $workerStatus = 0;
            pcntl_waitpid($workerPid, $workerStatus);
            if (null !== $rearmPid) {
                $rearmStatus = 0;
                pcntl_waitpid($rearmPid, $rearmStatus);
            }

            unset($this->serviceOverrides[SourceDeleteArtifactsPort::class]);
            $this->rebuildContainer();
            @unlink($backendPidPath);
            @unlink($checkpointPath);
            @unlink($releasePath);
            @unlink($rearmStartedPath);
        }

        self::assertFileExists($rearmDonePath);
        $rearmedRunId = (string) file_get_contents($rearmDonePath);
        @unlink($rearmDonePath);
        self::assertNotSame('', $rearmedRunId);
        self::assertNotSame($deletionRunId, $rearmedRunId);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);
        self::assertNotNull($sourceApiFacade->findSource($sourceDto->id));
    }

    /**
     * @return list<int>
     */
    private function initiateDeletionConcurrently(
        SourceDto $sourceDto,
        string $errorPath,
    ): array {
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

                $this->initiateDeletionInChild($sourceDto->id, $errorPath);
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

    private function initiateDeletionInChild(string $sourceId, string $errorPath): never
    {
        try {
            $service = $this->getService(SourceDeletionRecoveryService::class);
            if (! $service instanceof SourceDeletionRecoveryService) {
                throw new RuntimeException('SourceDeletionRecoveryService is not configured.');
            }

            $service->initiateDeletion($sourceId, self::ADMIN_USER_ID);

            exit(0);
        } catch (SourceDeletionRecoveryException) {
            exit(2);
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
     * @return list<AuditEntryDto>
     */
    private function auditEntries(OrganizationDto $organizationDto, SourceDto $sourceDto): array
    {
        $audit = $this->getService(AuditApiFacadeInterface::class);
        self::assertInstanceOf(AuditApiFacadeInterface::class, $audit);

        $entries = $audit->recentBySource($organizationDto->id, $sourceDto->id, 20);
        self::assertGreaterThanOrEqual(1, count($entries));

        return $entries;
    }

    /**
     * Reads the status straight from PostgreSQL: the forked children applied
     * their updates through raw conditional SQL, so the parent ORM heap may
     * still cache the pre-initiation entity.
     */
    private function rawSourceStatus(string $sourceId): string
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        $rows = $databaseProvider->database()->select('status')
            ->from('sources')
            ->where('uuid', $sourceId)
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
