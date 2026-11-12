<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Common\Security\SecretEncryptorInterface;
use Common\Security\SodiumSecretEncryptor;
use Packio\Source\Application\Command\RegisterSourceCommand;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Command\SourceMaintenanceService;
use Packio\Source\Application\Dto\MembershipData;
use Packio\Source\Application\Dto\SourceCreationExecutionResult;
use Packio\Source\Application\Dto\WebhookRegistrationResult;
use Packio\Source\Application\Port\JobSchedulerPort;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationMembershipPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Application\Security\ProviderAuthorizationFactory;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SourceAuthorizationInvalidationService;
use Packio\Source\Application\Service\SourceCreationExecutionService;
use Packio\Source\Application\Service\SourceCreationOutcome;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipVerifierInterface;
use Packio\Source\Application\Service\SourceOAuthService;
use Packio\Source\Application\Service\SourceWebhookRegistrationOutcome;
use Packio\Source\Application\Service\WebhookAutoRegistrationService;
use Packio\Source\Application\Service\WebhookRegistrarInterface;
use Packio\Source\Application\Service\WebhookRegistrarRegistryInterface;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Provider\SourceProviderInterface;
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

#[CoversClass(SourceCreationExecutionService::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceCreationExecutionServiceTest extends TestCase
{
    use InertSourceCollaborators;

    private MockObject&SourceRepositoryInterface $sourceRepository;
    private MockObject&ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository;
    private MockObject&SourceOAuthAccountRepositoryInterface $sourceOAuthAccountRepository;
    private MockObject&SourceProviderRegistryInterface $providers;
    private MockObject&WebhookRegistrarRegistryInterface $webhooks;
    private MockObject&OrganizationMembershipPort $membership;
    private JobSchedulerPort&MockObject $queue;
    private Source $source;

    protected function setUp(): void
    {
        $this->sourceRepository                = $this->createMock(SourceRepositoryInterface::class);
        $this->providerAuthorizationRepository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $this->sourceOAuthAccountRepository    = $this->createMock(SourceOAuthAccountRepositoryInterface::class);
        $this->providers                       = $this->createMock(SourceProviderRegistryInterface::class);
        $this->webhooks                        = $this->createMock(WebhookRegistrarRegistryInterface::class);
        $this->membership                      = $this->createMock(OrganizationMembershipPort::class);
        $this->queue                           = $this->createMock(JobSchedulerPort::class);
        $this->source                          = new Source('org-1', 'github', 'https://github.com/acme/package', 'acme/package');
        $this->source->setIdentifier(Uuid::uuid7());
        $this->sourceRepository->method('save')->willReturnCallback(static fn (Source $source): Source => $source);
        $this->sourceRepository->method('findById')->willReturn($this->source);
    }

    #[Test]
    public function rejectsMissingOwnerBeforeRegistration(): void
    {
        $this->providers->expects(self::never())->method('provider');
        $this->queue->expects(self::never())->method('enqueue');
        self::assertSame(SourceCreationOutcome::Forbidden, $this->execute()->outcome);
    }

    #[Test]
    #[DataProvider('webhookResults')]
    public function preservesCreatedSourceAndSchedulesSyncWithTypedWebhookState(
        WebhookRegistrationResult $webhookRegistrationResult,
        SourceWebhookRegistrationOutcome $sourceWebhookRegistrationOutcome
    ): void {
        $this->configureOwner();
        $this->webhooks->method('hasProvider')->willReturn(true);
        $registrar = $this->createMock(WebhookRegistrarInterface::class);
        $this->webhooks->method('forProvider')->willReturn($registrar);
        $registrar->expects(self::once())->method('register')->willReturn($webhookRegistrationResult);
        $this->queue->expects(self::once())->method('enqueue')->with('org-1', 'source.sync', [
            'source_id'       => $this->source->getIdentifier()->toString(),
            'organization_id' => 'org-1',
        ], 5);
        $sourceCreationExecutionResult = $this->execute();
        self::assertSame(SourceCreationOutcome::Created, $sourceCreationExecutionResult->outcome);
        self::assertSame($sourceWebhookRegistrationOutcome, $sourceCreationExecutionResult->webhook);
    }

    /** @return list<array{WebhookRegistrationResult, SourceWebhookRegistrationOutcome}> */
    public static function webhookResults(): array
    {
        return [
            [new WebhookRegistrationResult(true, 'hook-1', '', false), SourceWebhookRegistrationOutcome::Registered],
            [new WebhookRegistrationResult(true, 'hook-1', '', true), SourceWebhookRegistrationOutcome::AlreadyRegistered],
            [new WebhookRegistrationResult(false, '', 'unavailable', false), SourceWebhookRegistrationOutcome::Warning],
        ];
    }

    #[Test]
    public function retryAfterQueueFailureReusesRegisteredSource(): void
    {
        $this->configureOwner();
        $this->sourceRepository->expects(self::exactly(2))->method('findByUrl')->willReturn(null, $this->source);
        $attempt = 0;
        $this->queue->expects(self::exactly(2))->method('enqueue')->willReturnCallback(static function() use (&$attempt): void {
            if (0 === $attempt++) {
                throw new RuntimeException('queue unavailable');
            }
        });
        self::assertSame(SourceCreationOutcome::RetryableFailure, $this->execute()->outcome);
        $sourceCreationExecutionResult = $this->execute();
        self::assertSame(SourceCreationOutcome::Created, $sourceCreationExecutionResult->outcome);
        self::assertSame($this->source->getIdentifier()->toString(), $sourceCreationExecutionResult->data['source_id']);
        self::assertSame(SourceWebhookRegistrationOutcome::Skipped, $sourceCreationExecutionResult->webhook);
    }

    #[Test]
    public function unexpectedWebhookSetupFailureDoesNotPreventInitialSync(): void
    {
        $this->configureOwner()->method('generateWebhookSecret')->willThrowException(new RuntimeException('secret generation failed'));
        $this->webhooks->method('hasProvider')->willReturn(true);
        $this->queue->expects(self::once())->method('enqueue');
        $sourceCreationExecutionResult = $this->execute();
        self::assertSame(SourceCreationOutcome::Created, $sourceCreationExecutionResult->outcome);
        self::assertSame(SourceWebhookRegistrationOutcome::Warning, $sourceCreationExecutionResult->webhook);
    }

    private function configureOwner(): MockObject&SourceProviderInterface
    {
        $this->membership->method('findMembership')->willReturn(new MembershipData('org-1', 'user-1', 'owner'));
        $provider = $this->createMock(SourceProviderInterface::class);
        $provider->method('registerSource')->willReturn($this->source);
        $this->providers->method('provider')->willReturn($provider);

        return $provider;
    }

    private function execute(): SourceCreationExecutionResult
    {
        return new SourceCreationExecutionService($this->commandService(), $this->membership, $this->queue)
            ->execute(new RegisterSourceCommand('org-1', 'github', 'https://github.com/acme/package', 'acme/package'), 'user-1', new NullLogger())
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
