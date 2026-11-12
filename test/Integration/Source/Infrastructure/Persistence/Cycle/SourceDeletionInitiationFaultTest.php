<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Admin\Infrastructure\SourceDeletion\SourceDeletionRecoveryService;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Application\Service\SourceJobScheduler;
use Packio\Worker\Application\JobQueueService;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;

use function bin2hex;
use function random_bytes;

final class SourceDeletionInitiationFaultTest extends IntegrationTestCase
{
    #[Test]
    public function aFailedJobEnqueueLeavesTheCommittedGenerationRecoverable(): void
    {
        $jobRepository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobRepository);

        $this->serviceOverrides[JobQueueService::class] = new class($jobRepository) extends JobQueueService {
            public function enqueueOnceHeld(
                string $organizationId,
                string $type,
                string $idempotencyKey,
                array $payload,
                int $priority = 0,
                ?Chronos $scheduledAt = null,
                int $maxAttempts = 3,
            ): Job {
                throw new RuntimeException('Injected enqueue failure.');
            }
        };
        $this->rebuildContainer();

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-initiation-fault-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-initiation-fault-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));

        try {
            $sourceApiFacade->initiateSourceDelete($organizationDto->id, $sourceDto->id, 'admin-source-delete-initiation');
            self::fail('Expected the injected enqueue failure to propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected enqueue failure.', $exception->getMessage());
        }

        self::assertSame('deleting', $this->rawSourceStatus($sourceDto->id));
        self::assertSame([], $jobRepository->findSourceDeleteJobs($organizationDto->id, $sourceDto->id));

        unset($this->serviceOverrides[JobQueueService::class]);
        $this->rebuildContainer();
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);
        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));
    }

    #[Test]
    public function anAuditFailureCannotExposeTheCleanupJobAndDoesNotRevertWhenItsCancellationFails(): void
    {
        $jobRepository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobRepository);

        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::once())
            ->method('recordOnce')
            ->willThrowException(new RuntimeException('Injected audit failure.'))
        ;

        $this->serviceOverrides[AuditApiFacadeInterface::class] = $auditApiFacade;
        $this->serviceOverrides[JobQueueService::class]         = new class($jobRepository) extends JobQueueService {
            public function cancel(string $jobId): bool
            {
                return false;
            }
        };
        $this->rebuildContainer();

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);
        $sourceDeletionRecoveryService = $this->getService(SourceDeletionRecoveryService::class);
        self::assertInstanceOf(SourceDeletionRecoveryService::class, $sourceDeletionRecoveryService);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-audit-fault-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-audit-fault-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));

        try {
            $sourceDeletionRecoveryService->initiateDeletion($sourceDto->id, 'admin-audit-fault');
            self::fail('Expected the injected audit failure to propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected audit failure.', $exception->getMessage());
        }

        self::assertSame('deleting', $this->rawSourceStatus($sourceDto->id));

        $sourceDeleteJobs = $jobRepository->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(1, $sourceDeleteJobs);
        self::assertSame(Job::STATUS_HELD, $sourceDeleteJobs[0]->status());
    }

    #[Test]
    public function aRepeatedProducerCompletesAnUnpublishedHeldLifecycle(): void
    {
        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-held-retry-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-held-retry-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));

        $sourceDeletionInitiationResult = $sourceApiFacade->initiateSourceDelete($organizationDto->id, $sourceDto->id);
        self::assertNotNull($sourceDeletionInitiationResult->jobId);
        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $sourceDeleteJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(1, $sourceDeleteJobs);
        self::assertSame(Job::STATUS_PENDING, $sourceDeleteJobs[0]->status());
    }

    #[Test]
    public function aRepeatedProducerPublishesTheExactLifecycleAfterAuditWasConfirmed(): void
    {
        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-audited-retry-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-audited-retry-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));

        $sourceDeletionInitiationResult = $sourceApiFacade->initiateSourceDelete($organizationDto->id, $sourceDto->id);
        self::assertNotNull($sourceDeletionInitiationResult->jobId);
        self::assertTrue($this->getService(SourceJobScheduler::class)->confirmInitiationAudit($organizationDto->id, $sourceDeletionInitiationResult->jobId));

        // Simulates a crash after durable audit confirmation but before
        // publish. Retrying the producer must resume this exact run.
        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $sourceDeleteJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(1, $sourceDeleteJobs);
        self::assertSame(Job::STATUS_PENDING, $sourceDeleteJobs[0]->status());
    }

    #[Test]
    public function aRetryCreatesTheMissingLifecycleJobAfterTheStatusTransitionCommitted(): void
    {
        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-transition-crash-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-transition-crash-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));
        $deletionRunId = Uuid::uuid7()->toString();

        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $databaseProvider->database()->update('sources')->values([
            'status'          => 'deleting',
            'active'          => false,
            'deletion_run_id' => $deletionRunId,
        ])->where('uuid', $sourceDto->id)->run();
        $this->rebuildContainer();
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $sourceDeleteJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(1, $sourceDeleteJobs);
        self::assertSame('source-delete:' . $sourceDto->id . ':' . $deletionRunId, $sourceDeleteJobs[0]->idempotencyKey());
        self::assertSame(Job::STATUS_PENDING, $sourceDeleteJobs[0]->status());
    }

    #[Test]
    public function aRetryPublishesAfterAuditWasRecordedBeforeTheInitiatorCrashed(): void
    {
        $auditApiFacade = $this->getService(AuditApiFacadeInterface::class);
        self::assertInstanceOf(AuditApiFacadeInterface::class, $auditApiFacade);

        $failingAuditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $failingAuditApiFacade->expects(self::once())
            ->method('recordOnce')
            ->willReturnCallback(static function(...$arguments) use ($auditApiFacade): bool {
                $auditApiFacade->recordOnce(...$arguments);

                throw new RuntimeException('Injected crash after durable audit record.');
            })
        ;
        $this->serviceOverrides[AuditApiFacadeInterface::class] = $failingAuditApiFacade;
        $this->rebuildContainer();

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'source-audit-crash-' . bin2hex(random_bytes(6)),
        );
        $sourceDto = $sourceApiFacade->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/org/source-audit-crash-' . bin2hex(random_bytes(4)),
            organizationId: $organizationDto->id,
        ));

        try {
            $sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id);
            self::fail('Expected the injected post-audit crash.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected crash after durable audit record.', $exception->getMessage());
        }

        unset($this->serviceOverrides[AuditApiFacadeInterface::class]);
        $this->rebuildContainer();
        $sourceApiFacade = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sourceApiFacade);

        self::assertTrue($sourceApiFacade->scheduleSourceDelete($organizationDto->id, $sourceDto->id));

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $sourceDeleteJobs = $jobs->findSourceDeleteJobs($organizationDto->id, $sourceDto->id);
        self::assertCount(1, $sourceDeleteJobs);
        self::assertSame(Job::STATUS_PENDING, $sourceDeleteJobs[0]->status());
    }

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
}
