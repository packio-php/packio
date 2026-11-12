<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Application\Command;

use Cycle\Database\DatabaseProviderInterface;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Organization\Api\Dto\OrganizationDeletionContext;
use Packio\Organization\Api\Dto\OrganizationDeletionInitiationOutcome;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class OrganizationDeletionInitiatorTest extends IntegrationTestCase
{
    #[DataProvider('origins')]
    public function testRepeatPublishesOneJobAndRecordsOneAudit(string $origin, string $event, string $actionOrigin): void
    {
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizations);
        $organizationDto                      = $organizations->registerOrganization('initiation-' . Uuid::uuid7()->toString());
        $actorId                              = Uuid::uuid7()->toString();
        $organizationDeletionContext          = new OrganizationDeletionContext($organizationDto->id, $actorId, $origin, $event);
        $organizationDeletionInitiationResult = $organizations->initiateOrganizationDeletion($organizationDeletionContext);
        $repeat                               = $organizations->initiateOrganizationDeletion($organizationDeletionContext);
        self::assertSame(OrganizationDeletionInitiationOutcome::Initiated, $organizationDeletionInitiationResult->outcome);
        self::assertSame(OrganizationDeletionInitiationOutcome::AlreadyInProgress, $repeat->outcome);
        self::assertSame($organizationDeletionInitiationResult->jobId, $repeat->jobId);
        self::assertSame($organizationDeletionInitiationResult->deletionRunId, $repeat->deletionRunId);
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $scheduled = $jobs->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ]);
        self::assertCount(1, $scheduled);
        self::assertSame('pending', $scheduled[0]->status());
        $audit = $this->getService(AuditApiFacadeInterface::class);
        self::assertInstanceOf(AuditApiFacadeInterface::class, $audit);
        $entries = $audit->recentByOrganization($organizationDto->id);
        self::assertCount(1, $entries);
        self::assertSame($event, $entries[0]->event);
        self::assertSame($actorId, $entries[0]->actorId);
        self::assertSame($actionOrigin, $entries[0]->context['action_origin']);
        self::assertSame($organizationDeletionInitiationResult->deletionRunId, $entries[0]->context['deletion_run_id']);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function origins(): iterable
    {
        yield 'organization' => ['organization', 'organization.delete.initiated', 'organization-delete'];

        yield 'admin' => ['admin', 'organization.delete.initiated_by_admin', 'admin-organization-delete'];

        yield 'api' => ['api', 'organization.delete.initiated', 'api-organization-delete'];
    }

    public function testAuditFailureCancelsHeldJobAndRestoresOrganization(): void
    {
        $auditApiFacade = $this->createMock(AuditApiFacadeInterface::class);
        $auditApiFacade->expects(self::once())->method('recordOnce')->willThrowException(new RuntimeException('audit unavailable'));
        $this->serviceOverrides[AuditApiFacadeInterface::class] = $auditApiFacade;
        $this->rebuildContainer();
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizations);
        $organizationDto = $organizations->registerOrganization('audit-failure-' . Uuid::uuid7()->toString());

        try {
            $organizations->initiateOrganizationDeletion(new OrganizationDeletionContext($organizationDto->id));
            self::fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('audit unavailable', $exception->getMessage());
        }
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $rows = $databaseProvider->database()->select(['status', 'active', 'deletion_run_id'])->from('organizations')->where('uuid', $organizationDto->id)->fetchAll();
        self::assertSame('active', $rows[0]['status']);
        self::assertTrue($rows[0]['active']);
        self::assertNull($rows[0]['deletion_run_id']);
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $scheduled = $jobs->findByOrganization($organizationDto->id, [
            'type' => 'organization.delete',
        ]);
        self::assertCount(1, $scheduled);
        self::assertSame('canceled', $scheduled[0]->status());
    }
}
