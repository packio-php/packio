<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseProviderInterface;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;

use function array_map;
use function bin2hex;
use function random_bytes;
use function sort;

#[AllowMockObjectsWithoutExpectations]
final class OrganizationDeletionInitiationFaultTest extends IntegrationTestCase
{
    #[Test]
    public function aFailedJobEnqueueLeavesTheCommittedGenerationRecoverable(): void
    {
        $jobApiFacade = $this->createMock(JobApiFacadeInterface::class);
        $jobApiFacade->method('enqueueOnceHeld')
            ->willThrowException(new RuntimeException('Injected enqueue failure.'))
        ;

        $this->serviceOverrides[JobApiFacadeInterface::class] = $jobApiFacade;
        $this->rebuildContainer();

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'org-initiation-fault-' . bin2hex(random_bytes(6)),
        );

        try {
            $organizationApiFacade->deleteOrganizationWithOutcome($organizationDto->id);
            self::fail('Expected the injected enqueue failure to propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected enqueue failure.', $exception->getMessage());
        }

        self::assertSame('deleting', $this->rawOrganizationStatus($organizationDto->id));

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        self::assertSame([], $jobs->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ]));

        unset($this->serviceOverrides[JobApiFacadeInterface::class]);
        $this->rebuildContainer();
        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $organizationDeletionInitiationResult = $organizationApiFacade->deleteOrganizationWithOutcome($organizationDto->id);
        self::assertNotNull($organizationDeletionInitiationResult->jobId);
    }

    #[Test]
    public function aRetryAfterSuccessfulCompensationUsesANewLifecycleJobGeneration(): void
    {
        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'org-initiation-retry-' . bin2hex(random_bytes(6)),
        );

        $organizationDeletionInitiationResult = $organizationApiFacade->deleteOrganizationWithOutcome($organizationDto->id);
        self::assertNotNull($organizationDeletionInitiationResult->jobId);
        self::assertTrue($organizationApiFacade->revertInitiation(
            $organizationDto->id,
            $organizationDeletionInitiationResult->previousActive,
            $organizationDeletionInitiationResult->jobId,
            $organizationDeletionInitiationResult->deletionRunId,
        ));

        $second = $organizationApiFacade->deleteOrganizationWithOutcome($organizationDto->id);
        self::assertNotNull($second->jobId);
        self::assertNotSame($organizationDeletionInitiationResult->jobId, $second->jobId);

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $organizationJobs = $jobs->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ]);
        self::assertCount(2, $organizationJobs);
        $statuses = array_map(static fn ($job): string => $job->status(), $organizationJobs);
        sort($statuses);
        self::assertSame(['canceled', 'held'], $statuses);
    }

    #[Test]
    public function aRetryPublishesAnOrganizationLifecycleAfterItsAuditWasRecorded(): void
    {
        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'org-audited-retry-' . bin2hex(random_bytes(6)),
        );

        $organizationDeletionInitiationResult = $organizationApiFacade->deleteOrganizationWithOutcome($organizationDto->id);
        self::assertNotNull($organizationDeletionInitiationResult->jobId);
        $auditApiFacade = $this->getService(AuditApiFacadeInterface::class);
        self::assertInstanceOf(AuditApiFacadeInterface::class, $auditApiFacade);
        self::assertTrue($auditApiFacade->recordOnce(
            'organization-delete-admin-initiation:' . $organizationDto->id . ':' . $organizationDeletionInitiationResult->deletionRunId,
            'organization.delete.initiated_by_admin',
            [
                'queued' => true,
            ],
            organizationId: $organizationDto->id,
            subjectType: 'organization',
            subjectId: $organizationDto->id,
        ));

        // Simulates a crash immediately after durable audit recording. The
        // next administrative request must publish the same generation.
        $retry = $organizationApiFacade->deleteOrganizationWithOutcome($organizationDto->id);
        self::assertNotNull($retry->jobId);
        self::assertSame($organizationDeletionInitiationResult->jobId, $retry->jobId);
        self::assertTrue($organizationApiFacade->publishInitiationAfterAudit($organizationDto->id, $retry->jobId));

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $organizationJobs = $jobs->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ]);
        self::assertCount(1, $organizationJobs);
        self::assertSame('pending', $organizationJobs[0]->status());
    }

    #[Test]
    public function aRetryCreatesTheMissingOrganizationLifecycleJobAfterTheStatusTransitionCommitted(): void
    {
        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);

        $organizationDto = $organizationApiFacade->registerOrganization(
            'org-transition-crash-' . bin2hex(random_bytes(6)),
        );
        $deletionRunId = Uuid::uuid7()->toString();

        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $databaseProvider->database()->update('organizations')->values([
            'status'          => 'deleting',
            'active'          => false,
            'deletion_run_id' => $deletionRunId,
        ])->where('uuid', $organizationDto->id)->run();
        $this->rebuildContainer();

        $organizationApiFacade = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizationApiFacade);
        $organizationDeletionInitiationResult = $organizationApiFacade->deleteOrganizationWithOutcome($organizationDto->id);
        self::assertSame('already_deleting', $organizationDeletionInitiationResult->outcome->value);
        self::assertNotNull($organizationDeletionInitiationResult->jobId);

        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $organizationJobs = $jobs->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ]);
        self::assertCount(1, $organizationJobs);
        self::assertSame('organization-delete:' . $organizationDto->id . ':' . $deletionRunId, $organizationJobs[0]->idempotencyKey());
        self::assertSame('held', $organizationJobs[0]->status());
    }

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
}
