<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Common\Security\SecretEncryptorInterface;
use Common\Security\SodiumSecretEncryptor;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Command\SourceMaintenanceService;
use Packio\Source\Application\Dto\SourceDeleteArtifactsResult;
use Packio\Source\Application\Dto\SourceDeletionExecutionResult;
use Packio\Source\Application\Exception\SourceDeletionExecutionLeaseLostException;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Port\SourceDeleteArtifactsPort;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Application\Security\ProviderAuthorizationFactory;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SourceAuthorizationInvalidationService;
use Packio\Source\Application\Service\SourceDeletionExecutionLeaseInterface;
use Packio\Source\Application\Service\SourceDeletionExecutionService;
use Packio\Source\Application\Service\SourceDeletionOutcome;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipVerifierInterface;
use Packio\Source\Application\Service\SourceOAuthService;
use Packio\Source\Application\Service\WebhookAutoRegistrationService;
use Packio\Source\Application\Service\WebhookRegistrarInterface;
use Packio\Source\Application\Service\WebhookRegistrarRegistryInterface;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Source\Domain\Service\SourceAuthorizationStrategyResolver;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use RuntimeException;

#[CoversClass(SourceDeletionExecutionService::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceDeletionExecutionServiceTest extends TestCase
{
    use InertSourceCollaborators;

    private MockObject&SourceDeletionExecutionLeaseInterface $lease;
    private MockObject&SourceDeleteArtifactsPort $artifacts;
    private MockObject&SourceRepositoryInterface $sourceRepository;
    private MockObject&ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository;
    private MockObject&SourceOAuthAccountRepositoryInterface $sourceOAuthAccountRepository;
    private MockObject&SourceProviderRegistryInterface $providers;
    private MockObject&WebhookRegistrarRegistryInterface $webhooks;
    private Source $source;

    protected function setUp(): void
    {
        $this->sourceRepository                = $this->createMock(SourceRepositoryInterface::class);
        $this->providerAuthorizationRepository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $this->sourceOAuthAccountRepository    = $this->createMock(SourceOAuthAccountRepositoryInterface::class);
        $this->providers                       = $this->createMock(SourceProviderRegistryInterface::class);
        $this->webhooks                        = $this->createMock(WebhookRegistrarRegistryInterface::class);
        $this->lease                           = $this->createMock(SourceDeletionExecutionLeaseInterface::class);
        $this->artifacts                       = $this->createMock(SourceDeleteArtifactsPort::class);
        $this->source                          = new Source('org-1', 'github', 'https://github.com/acme/package', 'acme/package');
        $this->source->setIdentifier(Uuid::uuid7());
        $this->sourceRepository->method('save')->willReturnCallback(static fn (Source $source): Source => $source);
        $this->sourceRepository->method('findById')->willReturn($this->source);
    }

    #[Test]
    public function missingGenerationDoesNotAcquireLeaseOrDeleteArtifacts(): void
    {
        $this->lease->expects(self::never())->method('acquire');
        $this->artifacts->expects(self::never())->method('deleteRelatedArtifacts');
        self::assertSame(SourceDeletionOutcome::StaleLease, $this->execute('')->outcome);
    }

    #[Test]
    public function busyLeaseDoesNotMutateSource(): void
    {
        $this->lease->method('acquire')->willReturn(false);
        $this->sourceRepository->method('findByIdInOrganization')->willReturn($this->source);
        $this->lease->expects(self::never())->method('rollback');
        $this->artifacts->expects(self::never())->method('deleteRelatedArtifacts');
        self::assertSame(SourceDeletionOutcome::StaleLease, $this->execute()->outcome);
    }

    #[Test]
    public function repeatedDeletionOfAbsentSourceIsSuccessfulWithoutCleanup(): void
    {
        $this->lease->method('acquire')->willReturn(false);
        $this->artifacts->expects(self::never())->method('deleteRelatedArtifacts');
        self::assertSame(SourceDeletionOutcome::AlreadyDeleted, $this->execute()->outcome);
    }

    #[Test]
    public function commitsOnlyAfterCleanupAndDomainDeletion(): void
    {
        $calls = [];
        $this->lease->expects(self::once())->method('acquire')->willReturn(true);
        $this->lease->expects(self::exactly(2))->method('assertHeld')->willReturnCallback(static function() use (&$calls): void { $calls[] = 'assert'; });
        $this->artifacts->expects(self::once())->method('deleteRelatedArtifacts')->with('source-1', 'org-1', 'run-1', 'test')
            ->willReturnCallback(static function() use (&$calls): SourceDeleteArtifactsResult {
                $calls[] = 'artifacts';

                return new SourceDeleteArtifactsResult(1, 1, 2, 1, 0, 3);
            })
        ;
        $this->sourceRepository->method('findByIdInOrganization')->willReturn($this->source);
        $this->sourceRepository->method('isDeletionRunCurrent')->willReturn(true);
        $this->sourceRepository->expects(self::once())->method('deleteAndFlush')->willReturnCallback(static function() use (&$calls): void { $calls[] = 'delete'; });
        $this->lease->expects(self::once())->method('commit')->willReturnCallback(static function() use (&$calls): void { $calls[] = 'commit'; });
        $this->lease->expects(self::never())->method('rollback');
        $sourceDeletionExecutionResult = $this->execute();
        self::assertSame(SourceDeletionOutcome::Deleted, $sourceDeletionExecutionResult->outcome);
        self::assertSame(2, $sourceDeletionExecutionResult->data['artifacts']['releases_deleted']);
        self::assertSame(['assert', 'artifacts', 'assert', 'delete', 'commit'], $calls);
    }

    #[Test]
    public function blockedDeletionRollsBackArtifacts(): void
    {
        $this->lease->method('acquire')->willReturn(true);
        $this->artifacts->method('deleteRelatedArtifacts')->willReturn(new SourceDeleteArtifactsResult(1, 1, 0, 0, 0, 0));
        $this->sourceRepository->method('findByIdInOrganization')->willReturn($this->source);
        $this->sourceRepository->method('isDeletionRunCurrent')->willReturn(false);
        $this->sourceRepository->expects(self::never())->method('deleteAndFlush');
        $this->lease->expects(self::once())->method('rollback')->with('source-1', 'org-1', 'run-1');
        $this->lease->expects(self::never())->method('commit');
        self::assertSame(SourceDeletionOutcome::Blocked, $this->execute()->outcome);
    }

    #[Test]
    public function cleanupFailureRollsBackWithoutDeletingSource(): void
    {
        $this->lease->method('acquire')->willReturn(true);
        $this->artifacts->method('deleteRelatedArtifacts')->willThrowException(new RuntimeException('cleanup failed'));
        $this->sourceRepository->expects(self::never())->method('deleteAndFlush');
        $this->lease->expects(self::once())->method('rollback');
        $this->lease->expects(self::never())->method('commit');
        self::assertSame(SourceDeletionOutcome::RetryableFailure, $this->execute()->outcome);
    }

    #[Test]
    #[DataProvider('leaseLossPoints')]
    public function leaseLossRequiresRestartAfterRollback(string $point): void
    {
        if ('acquire' !== $point) {
            $this->lease->method('acquire')->willReturn(true);
            $this->artifacts->method('deleteRelatedArtifacts')->willReturn(new SourceDeleteArtifactsResult(0, 0, 0, 0, 0, 0));
        }
        $this->lease->method($point)->willThrowException(new SourceDeletionExecutionLeaseLostException('lost'));
        $this->lease->expects('acquire' === $point ? self::never() : self::once())->method('rollback');
        $this->expectException(SourceDeletionExecutionLeaseLostException::class);
        $this->execute();
    }

    /** @return list<array{string}> */
    public static function leaseLossPoints(): array
    {
        return [['acquire'], ['assertHeld'], ['commit']];
    }

    #[Test]
    public function leaseLossAfterArtifactsPreventsDomainDeletion(): void
    {
        $this->lease->method('acquire')->willReturn(true);
        $checks = 0;
        $this->lease->method('assertHeld')->willReturnCallback(static function() use (&$checks): void {
            if (2 === ++$checks) {
                throw new SourceDeletionExecutionLeaseLostException('lost after cleanup');
            }
        });
        $this->artifacts->expects(self::once())->method('deleteRelatedArtifacts')->willReturn(new SourceDeleteArtifactsResult(1, 1, 1, 0, 0, 0));
        $this->sourceRepository->expects(self::never())->method('deleteAndFlush');
        $this->lease->expects(self::once())->method('rollback');
        $this->lease->expects(self::never())->method('commit');
        $this->expectException(SourceDeletionExecutionLeaseLostException::class);
        $this->execute();
    }

    #[Test]
    public function domainDeletionFailureRollsBackAndCanBeRetried(): void
    {
        $this->lease->method('acquire')->willReturn(true);
        $this->artifacts->method('deleteRelatedArtifacts')->willReturn(new SourceDeleteArtifactsResult(1, 1, 0, 0, 0, 0));
        $this->sourceRepository->method('findByIdInOrganization')->willReturn($this->source);
        $this->sourceRepository->method('isDeletionRunCurrent')->willReturn(true);
        $attempt = 0;
        $this->sourceRepository->expects(self::exactly(2))->method('deleteAndFlush')->willReturnCallback(static function() use (&$attempt): void {
            if (0 === $attempt++) {
                throw new RuntimeException('database write failed');
            }
        });
        $this->lease->expects(self::once())->method('rollback');
        $this->lease->expects(self::once())->method('commit');
        self::assertSame(SourceDeletionOutcome::RetryableFailure, $this->execute()->outcome);
        self::assertSame(SourceDeletionOutcome::Deleted, $this->execute()->outcome);
    }

    #[Test]
    public function absentSourceAfterLeaseAcquisitionCommitsCleanup(): void
    {
        $this->lease->method('acquire')->willReturn(true);
        $this->artifacts->method('deleteRelatedArtifacts')->willReturn(new SourceDeleteArtifactsResult(0, 0, 0, 0, 0, 0));
        $this->sourceRepository->expects(self::never())->method('deleteAndFlush');
        $this->lease->expects(self::once())->method('commit');
        $this->lease->expects(self::never())->method('rollback');
        self::assertSame(SourceDeletionOutcome::AlreadyDeleted, $this->execute()->outcome);
    }

    #[Test]
    #[DataProvider('webhookDeletionResults')]
    public function webhookDeregistrationIsBestEffort(bool $deregistered): void
    {
        $this->lease->method('acquire')->willReturn(true);
        $this->artifacts->method('deleteRelatedArtifacts')->willReturn(new SourceDeleteArtifactsResult(0, 0, 0, 0, 0, 0));
        $this->source->replaceProviderConfig([
            'webhook_external_id' => 'hook-1',
        ]);
        $this->sourceRepository->method('findByIdInOrganization')->willReturn($this->source);
        $this->sourceRepository->method('isDeletionRunCurrent')->willReturn(true);
        $this->webhooks->method('hasProvider')->willReturn(true);
        $registrar = $this->createMock(WebhookRegistrarInterface::class);
        $registrar->expects(self::once())->method('deregister')->willReturn($deregistered);
        $this->webhooks->method('forProvider')->willReturn($registrar);
        $this->sourceRepository->expects(self::once())->method('deleteAndFlush');
        $this->lease->expects(self::once())->method('commit');
        $this->lease->expects(self::never())->method('rollback');
        $sourceDeletionExecutionResult = $this->execute();
        self::assertSame(SourceDeletionOutcome::Deleted, $sourceDeletionExecutionResult->outcome);
        self::assertSame($deregistered, $sourceDeletionExecutionResult->data['deregistered']);
    }

    /** @return list<array{bool}> */
    public static function webhookDeletionResults(): array
    {
        return [[true], [false]];
    }

    private function execute(string $generation = 'run-1'): SourceDeletionExecutionResult
    {
        return new SourceDeletionExecutionService($this->commandService(), $this->artifacts, $this->lease, $this->sourceRepository)
            ->execute('source-1', 'org-1', $generation, 'test', new NullLogger())
        ;
    }

    private function commandService(): SourceCommandService
    {
        $secretEncryptor = $this->createMock(SecretEncryptorInterface::class);

        $sourceOAuthService = new SourceOAuthService(
            $this->createMock(OAuthProviderPort::class),
            $this->sourceOAuthAccountRepository,
            new SourceOAuthAccountSecrets($secretEncryptor),
            $this->createMock(OrganizationQueryPort::class),
            $this->createStub(SourceProviderInstanceRepositoryInterface::class),
            $this->inertSourceProviderInstanceOAuthConfig(),
        );

        return new SourceCommandService(
            $this->sourceRepository,
            $this->providerAuthorizationRepository,
            $this->providers,
            new ProviderAuthorizationFactory(new ProviderAuthorizationSecrets($secretEncryptor)),
            new ProviderAuthorizationSecrets($secretEncryptor),
            new SourceAuthorizationStrategyResolver(),
            new SourceMaintenanceService($this->sourceRepository),
            $this->createMock(SourceOAuthAccountOwnershipVerifierInterface::class),
            $this->createMock(SourceProviderInstanceRepositoryInterface::class),
            sourceAuthorizationInvalidationService: new SourceAuthorizationInvalidationService(
                $this->sourceRepository,
                $this->providerAuthorizationRepository,
                $sourceOAuthService,
            ),
            webhookAutoRegistrationService: new WebhookAutoRegistrationService($this->webhooks, $this->sourceRepository, $this->inertSourceAuthorizationInvalidationService()),
            sourceWebhookSecrets: new SourceWebhookSecrets(new SodiumSecretEncryptor('test-webhook-key')),
        );
    }
}
