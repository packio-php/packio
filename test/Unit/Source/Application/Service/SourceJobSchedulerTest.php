<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Common\Security\SecretEncryptorInterface;
use Common\Security\SodiumSecretEncryptor;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Command\SourceMaintenanceService;
use Packio\Source\Application\Dto\ScheduledJob;
use Packio\Source\Application\Port\AuditRecorderPort;
use Packio\Source\Application\Port\JobSchedulerPort;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Application\Security\ProviderAuthorizationFactory;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SourceJobScheduler;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipVerifierInterface;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Source\Domain\Service\SourceAuthorizationStrategyResolver;
use Packio\Source\Domain\Value\SourceDeletionInitiation;
use Packio\Source\Domain\Value\SourceDeletionInitiationOutcome;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

use function count;

#[CoversClass(SourceJobScheduler::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceJobSchedulerTest extends TestCase
{
    use InertSourceCollaborators;

    private MockObject&SourceRepositoryInterface $sourceRepository;
    private JobSchedulerPort&MockObject $jobSchedulerPort;
    private AuditRecorderPort&MockObject $auditRecorderPort;
    private SourceJobScheduler $sourceJobScheduler;

    protected function setUp(): void
    {
        $this->sourceRepository       = $this->createMock(SourceRepositoryInterface::class);
        $this->jobSchedulerPort       = $this->createMock(JobSchedulerPort::class);
        $this->auditRecorderPort      = $this->createMock(AuditRecorderPort::class);
        $providerAuthorizationSecrets = new ProviderAuthorizationSecrets($this->createMock(SecretEncryptorInterface::class));

        // SourceCommandService is final: drive it through a mocked repository.
        $sourceCommandService = new SourceCommandService(
            $this->sourceRepository,
            $this->createMock(ProviderAuthorizationRepositoryInterface::class),
            $this->createMock(SourceProviderRegistryInterface::class),
            new ProviderAuthorizationFactory($providerAuthorizationSecrets),
            $providerAuthorizationSecrets,
            new SourceAuthorizationStrategyResolver(),
            new SourceMaintenanceService($this->sourceRepository),
            $this->createMock(SourceOAuthAccountOwnershipVerifierInterface::class),
            $this->createMock(SourceProviderInstanceRepositoryInterface::class),
            $this->inertSourceAuthorizationInvalidationService(),
            $this->inertWebhookAutoRegistrationService(),
            new SourceWebhookSecrets(new SodiumSecretEncryptor('test-webhook-key')),
        );

        $this->sourceJobScheduler = new SourceJobScheduler(
            $sourceCommandService,
            $this->jobSchedulerPort,
            $this->auditRecorderPort,
            new NullLogger(),
        );
    }

    #[Test]
    public function initiationEnqueuesHeldJobWithStableKey(): void
    {
        $this->sourceRepository->method('initiateDeletion')
            ->willReturn(new SourceDeletionInitiation(SourceDeletionInitiationOutcome::Initiated, true, 'run'))
        ;
        $this->jobSchedulerPort->expects(self::once())
            ->method('enqueueOnceHeld')
            ->with('org', 'source.delete', 'source-delete:src:run', [
                'source_id'       => 'src',
                'organization_id' => 'org',
                'origin'          => 'x',
                'deletion_run_id' => 'run',
            ], 10)
            ->willReturn('job')
        ;

        $sourceDeletionInitiationResult = $this->sourceJobScheduler->initiateSourceDelete('org', 'src', 'x');

        self::assertSame('job', $sourceDeletionInitiationResult->jobId);
        self::assertSame('run', $sourceDeletionInitiationResult->deletionRunId);
    }

    #[Test]
    public function recoveryReArmsTerminalJob(): void
    {
        $this->sourceRepository->expects(self::exactly(2))
            ->method('recoverDeletionRun')
            ->willReturnMap([
                ['src', 'org', false, 'run'],
                ['src', 'org', true, 'run2'],
            ])
        ;
        $keys = [];
        $this->jobSchedulerPort->expects(self::exactly(2))
            ->method('enqueueOnce')
            ->willReturnCallback(static function(string $organizationId, string $type, string $idempotencyKey) use (&$keys): ScheduledJob {
                $keys[] = $idempotencyKey;

                return 1 === count($keys) ? new ScheduledJob('j1', true) : new ScheduledJob('j2', false);
            })
        ;

        $sourceDeletionInitiationResult = $this->sourceJobScheduler->scheduleSourceDeletionRecovery('org', 'src', false, 'x');

        self::assertSame(['source-delete-recovery:src:run', 'source-delete-recovery:src:run2'], $keys);
        self::assertNotNull($sourceDeletionInitiationResult);
        self::assertSame('j2', $sourceDeletionInitiationResult->jobId);
        self::assertSame('run2', $sourceDeletionInitiationResult->deletionRunId);
    }

    #[Test]
    public function enqueueFailureIsRethrownWithoutRevert(): void
    {
        $this->sourceRepository->method('initiateDeletion')
            ->willReturn(new SourceDeletionInitiation(SourceDeletionInitiationOutcome::Initiated, true, 'run'))
        ;
        $this->jobSchedulerPort->method('enqueueOnceHeld')->willThrowException(new RuntimeException('queue down'));
        $this->sourceRepository->expects(self::never())->method('revertInitiation');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('queue down');

        $this->sourceJobScheduler->initiateSourceDelete('org', 'src', 'x');
    }

    #[Test]
    public function auditPrecedesPublish(): void
    {
        $this->sourceRepository->method('initiateDeletion')
            ->willReturn(new SourceDeletionInitiation(SourceDeletionInitiationOutcome::Initiated, true, 'run'))
        ;
        $this->jobSchedulerPort->method('enqueueOnceHeld')->willReturn('job');
        $calls = [];
        $this->auditRecorderPort->expects(self::once())
            ->method('recordOnce')
            ->with(
                'source-delete-initiation:src:run',
                'source.delete.initiated',
                [
                    'action_origin'   => 'organization-source-delete',
                    'deletion_run_id' => 'run',
                    'queued'          => true,
                ],
                'org',
                'src',
                'actor',
                'source',
                'src',
            )
            ->willReturnCallback(static function() use (&$calls): bool {
                $calls[] = 'audit';

                return true;
            })
        ;
        $this->jobSchedulerPort->expects(self::once())
            ->method('publishAfterAudit')
            ->with('job', 'org')
            ->willReturnCallback(static function() use (&$calls): bool {
                $calls[] = 'publish';

                return true;
            })
        ;

        self::assertTrue($this->sourceJobScheduler->scheduleSourceDeleteInContext(new ActorContext('org', 'actor', 'owner'), 'src'));
        self::assertSame(['audit', 'publish'], $calls);
    }
}
