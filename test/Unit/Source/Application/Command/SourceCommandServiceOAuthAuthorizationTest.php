<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Command;

use Common\Security\SecretEncryptorInterface;
use Common\Security\SodiumSecretEncryptor;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Application\Command\AuthorizeProviderCommand;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Command\SourceMaintenanceService;
use Packio\Source\Application\Dto\SourceOAuthAuthorizationData;
use Packio\Source\Application\Exception\SourceOAuthAuthorizationException;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Application\Security\ProviderAuthorizationFactory;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipDecision;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipVerifierInterface;
use Packio\Source\Application\Service\WebhookAutoRegistrationService;
use Packio\Source\Application\Service\WebhookRegistrarInterface;
use Packio\Source\Application\Service\WebhookRegistrarRegistryInterface;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Source\Domain\Service\SourceAuthorizationStrategyResolver;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

#[CoversClass(SourceCommandService::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceCommandServiceOAuthAuthorizationTest extends TestCase
{
    use InertSourceCollaborators;

    /** @var MockObject&SourceRepositoryInterface */
    private MockObject $sourceRepository;

    /** @var MockObject&ProviderAuthorizationRepositoryInterface */
    private MockObject $providerAuthorizationRepository;

    /** @var MockObject&SourceOAuthAccountOwnershipVerifierInterface */
    private MockObject $sourceOAuthAccountOwnershipVerifier;

    /** @var MockObject&SourceOAuthAccountRepositoryInterface */
    private MockObject $sourceOAuthAccountRepository;

    /** @var MockObject&SecretEncryptorInterface */
    private MockObject $secretEncryptor;

    protected function setUp(): void
    {
        $this->sourceRepository                    = $this->createMock(SourceRepositoryInterface::class);
        $this->providerAuthorizationRepository     = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $this->sourceOAuthAccountOwnershipVerifier = $this->createMock(SourceOAuthAccountOwnershipVerifierInterface::class);
        $this->sourceOAuthAccountRepository        = $this->createMock(SourceOAuthAccountRepositoryInterface::class);
        $this->secretEncryptor                     = $this->createMock(SecretEncryptorInterface::class);
        $this->secretEncryptor->method('encrypt')
            ->willReturnCallback(static fn (string $secret): string => 'enc_' . $secret)
        ;
    }

    #[Test]
    public function authorizesProviderFromSourceOAuthDecisionPayload(): void
    {
        $actorContext = new ActorContext('org-1', 'user-1', 'owner');
        $this->sourceOAuthAccountOwnershipVerifier->expects(self::once())
            ->method('verify')
            ->with($actorContext, 'user-1', 'github', 'soa-1')
            ->willReturn(SourceOAuthAccountOwnershipDecision::allowed(new SourceOAuthAuthorizationData(
                sourceOAuthAccountId: 'soa-1',
                accessToken: 'decrypted_source_token',
                providerUsername: 'ghuser',
                providerEmail: 'gh@example.com',
            )))
        ;

        $this->providerAuthorizationRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function(ProviderAuthorization $providerAuthorization): bool {
                self::assertSame('org-1', $providerAuthorization->organizationId());
                self::assertSame('github', $providerAuthorization->provider());
                self::assertSame('oauth2', $providerAuthorization->authStrategy());
                self::assertSame('ghuser', $providerAuthorization->accountName());
                self::assertSame('soa-1', $providerAuthorization->sourceOAuthAccountId());
                self::assertSame('enc_decrypted_source_token', $providerAuthorization->secret());

                return true;
            }))
            ->willReturnCallback(static fn (ProviderAuthorization $providerAuthorization): ProviderAuthorization => $providerAuthorization)
        ;

        $providerAuthorization = $this->commandService()->authorizeProviderFromSourceOAuth(
            $actorContext,
            'user-1',
            'github',
            'soa-1',
        );

        self::assertSame('soa-1', $providerAuthorization->sourceOAuthAccountId());
    }

    #[Test]
    public function authorizesSourceOAuthWithProviderInstanceOnlyInCurrentOrganization(): void
    {
        $providerInstanceId     = '00000000-0000-0000-0000-000000000901';
        $actorContext           = new ActorContext('org-1', 'user-1', 'owner');
        $sourceProviderInstance = SourceProviderInstance::create(
            organizationId: 'org-1',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: 'https://gitlab.company.test',
            clientId: 'client-id',
            clientSecret: null,
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2,
            createdByUserId: 'user-1',
        );
        $sourceProviderInstance->setIdentifier(Uuid::fromString($providerInstanceId));

        $this->sourceOAuthAccountOwnershipVerifier->expects(self::once())
            ->method('verify')
            ->with($actorContext, 'user-1', SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED, 'soa-1')
            ->willReturn(SourceOAuthAccountOwnershipDecision::allowed(new SourceOAuthAuthorizationData(
                sourceOAuthAccountId: 'soa-1',
                accessToken: 'decrypted_source_token',
                providerUsername: 'gitlab-user',
                providerEmail: 'gitlab@example.test',
                providerInstanceId: $providerInstanceId,
                providerBaseUrl: 'https://wrong.example.test',
            )))
        ;
        $sourceProviderInstanceRepository = $this->createMock(SourceProviderInstanceRepositoryInterface::class);
        $sourceProviderInstanceRepository->expects(self::once())
            ->method('findActiveByIdInOrganization')
            ->with($providerInstanceId, 'org-1')
            ->willReturn($sourceProviderInstance)
        ;

        $this->providerAuthorizationRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function(ProviderAuthorization $providerAuthorization) use ($providerInstanceId): bool {
                self::assertSame('org-1', $providerAuthorization->organizationId());
                self::assertSame(SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED, $providerAuthorization->provider());
                self::assertSame('oauth2', $providerAuthorization->authStrategy());
                self::assertSame($providerInstanceId, $providerAuthorization->providerInstanceId());
                self::assertSame('https://gitlab.company.test', $providerAuthorization->configuration()['base_url'] ?? null);
                self::assertSame($providerInstanceId, $providerAuthorization->configuration()['provider_instance_id'] ?? null);

                return true;
            }))
            ->willReturnCallback(static fn (ProviderAuthorization $providerAuthorization): ProviderAuthorization => $providerAuthorization)
        ;

        $providerAuthorization = $this->commandService(
            sourceProviderInstanceRepository: $sourceProviderInstanceRepository,
        )->authorizeProviderFromSourceOAuth(
            $actorContext,
            'user-1',
            SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            'soa-1',
        );

        self::assertSame($providerInstanceId, $providerAuthorization->providerInstanceId());
    }

    #[Test]
    public function rejectsSourceOAuthProviderInstanceFromAnotherOrganization(): void
    {
        $providerInstanceId = '00000000-0000-0000-0000-000000000901';
        $actorContext       = new ActorContext('org-2', 'user-1', 'owner');

        $this->sourceOAuthAccountOwnershipVerifier->expects(self::once())
            ->method('verify')
            ->willReturn(SourceOAuthAccountOwnershipDecision::allowed(new SourceOAuthAuthorizationData(
                sourceOAuthAccountId: 'soa-1',
                accessToken: 'decrypted_source_token',
                providerUsername: 'gitlab-user',
                providerEmail: 'gitlab@example.test',
                providerInstanceId: $providerInstanceId,
                providerBaseUrl: 'https://gitlab.org-a.test',
            )))
        ;
        $sourceProviderInstanceRepository = $this->createMock(SourceProviderInstanceRepositoryInterface::class);
        $sourceProviderInstanceRepository->expects(self::once())
            ->method('findActiveByIdInOrganization')
            ->with($providerInstanceId, 'org-2')
            ->willReturn(null)
        ;
        $this->providerAuthorizationRepository->expects(self::never())->method('save');

        $this->expectException(SourceOAuthAuthorizationException::class);
        $this->expectExceptionMessage('Source provider instance is not available.');

        $this->commandService(
            sourceProviderInstanceRepository: $sourceProviderInstanceRepository,
        )->authorizeProviderFromSourceOAuth(
            $actorContext,
            'user-1',
            SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            'soa-1',
        );
    }

    #[Test]
    public function sourceOAuthAuthorizationFailureDoesNotCreateProviderAuthorization(): void
    {
        $this->sourceOAuthAccountOwnershipVerifier->expects(self::once())
            ->method('verify')
            ->willReturn(SourceOAuthAccountOwnershipDecision::unavailable())
        ;
        $this->providerAuthorizationRepository->expects(self::never())->method('save');

        $this->expectException(SourceOAuthAuthorizationException::class);
        $this->expectExceptionMessage('Source OAuth account is not available.');

        $this->commandService()->authorizeProviderFromSourceOAuth(
            new ActorContext('org-1', 'user-1', 'owner'),
            'user-1',
            'github',
            'soa-1',
        );
    }

    #[Test]
    public function authorizesGitLabSelfHostedTokenWithProviderInstance(): void
    {
        $providerInstanceId     = '00000000-0000-0000-0000-000000000901';
        $sourceProviderInstance = SourceProviderInstance::create(
            organizationId: 'org-1',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: 'https://gitlab.company.test',
            clientId: 'client-id',
            clientSecret: null,
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
            createdByUserId: 'user-1',
        );
        $sourceProviderInstance->setIdentifier(Uuid::fromString($providerInstanceId));

        $sourceProviderInstanceRepository = $this->createMock(SourceProviderInstanceRepositoryInterface::class);
        $sourceProviderInstanceRepository->expects(self::once())
            ->method('findActiveByIdInOrganization')
            ->with($providerInstanceId, 'org-1')
            ->willReturn($sourceProviderInstance)
        ;

        $this->providerAuthorizationRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function(ProviderAuthorization $providerAuthorization) use ($providerInstanceId): bool {
                self::assertSame('org-1', $providerAuthorization->organizationId());
                self::assertSame('gitlab_self_hosted', $providerAuthorization->provider());
                self::assertSame('gitlab_self_hosted_token', $providerAuthorization->authStrategy());
                self::assertSame($providerInstanceId, $providerAuthorization->providerInstanceId());
                self::assertSame('enc_manual-token', $providerAuthorization->secret());
                self::assertSame('https://gitlab.company.test', $providerAuthorization->configuration()['base_url'] ?? null);
                self::assertSame($providerInstanceId, $providerAuthorization->configuration()['provider_instance_id'] ?? null);

                return true;
            }))
            ->willReturnCallback(static fn (ProviderAuthorization $providerAuthorization): ProviderAuthorization => $providerAuthorization)
        ;

        $authorization = $this->commandService(
            sourceProviderInstanceRepository: $sourceProviderInstanceRepository,
        )->authorizeProviderInContext(
            new ActorContext('org-1', 'user-1', 'owner'),
            new AuthorizeProviderCommand(
                organizationId: 'org-1',
                provider: 'gitlab_self_hosted',
                authorizationStrategy: 'token',
                payload: [
                    'token'                => 'manual-token',
                    'base_url'             => 'https://wrong.example.test',
                    'provider_instance_id' => $providerInstanceId,
                ],
            ),
        );

        self::assertSame($providerInstanceId, $authorization->providerInstanceId());
    }

    #[Test]
    public function deletingOAuthProviderAuthorizationLeavesCanonicalSourceOAuthAccount(): void
    {
        $providerAuthorization = ProviderAuthorization::authorizeFromOAuth(
            'org-1',
            'gitlab_self_hosted',
            'soa-1',
            'oauth2',
            [
                'token' => 'enc_token',
            ],
            'petrom',
        );
        $providerAuthorization->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000101'));

        $this->providerAuthorizationRepository->expects(self::once())
            ->method('findByIdInOrganization')
            ->with('auth-1', 'org-1')
            ->willReturn($providerAuthorization)
        ;
        $this->sourceRepository->expects(self::once())
            ->method('findByProviderAuthorizationId')
            ->with('auth-1', 'org-1')
            ->willReturn([])
        ;
        $this->providerAuthorizationRepository->expects(self::once())
            ->method('deleteAndFlush')
            ->with($providerAuthorization)
        ;
        $this->sourceOAuthAccountRepository->expects(self::never())
            ->method('deleteAndFlush')
        ;

        self::assertSame(
            [
                'success'    => true,
                'reassigned' => 0,
            ],
            $this->commandService()->reassignAndDeleteProviderAuthorization('auth-1', 'org-1', null),
        );
    }

    #[Test]
    public function deletingOAuthProviderAuthorizationDoesNotInspectSharedSourceOAuthAccount(): void
    {
        $providerAuthorization = ProviderAuthorization::authorizeFromOAuth(
            'org-1',
            'gitlab_self_hosted',
            'soa-1',
            'oauth2',
            [
                'token' => 'enc_token',
            ],
            'petrom',
        );
        $providerAuthorization->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000101'));
        $this->providerAuthorizationRepository->expects(self::once())
            ->method('findByIdInOrganization')
            ->with('auth-1', 'org-1')
            ->willReturn($providerAuthorization)
        ;
        $this->sourceRepository->expects(self::once())
            ->method('findByProviderAuthorizationId')
            ->with('auth-1', 'org-1')
            ->willReturn([])
        ;
        $this->sourceOAuthAccountRepository->expects(self::never())
            ->method('findById')
        ;
        $this->providerAuthorizationRepository->expects(self::once())
            ->method('deleteAndFlush')
            ->with($providerAuthorization)
        ;
        $this->sourceOAuthAccountRepository->expects(self::never())
            ->method('deleteAndFlush')
        ;

        self::assertSame(
            [
                'success'    => true,
                'reassigned' => 0,
            ],
            $this->commandService()->reassignAndDeleteProviderAuthorization('auth-1', 'org-1', null),
        );
    }

    #[Test]
    public function deleteSourceContinuesWhenWebhookDeregistrationFails(): void
    {
        $source = Source::register(
            organizationId: 'org-1',
            provider: 'codeberg',
            canonicalUrl: 'https://codeberg.org/sirix/package',
            displayName: 'sirix/package',
            defaultRef: 'main',
            providerConfig: [
                'owner'               => 'sirix',
                'repository'          => 'package',
                'webhook_external_id' => '42',
            ],
        );
        $source->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000201'));

        $registrar = $this->createMock(WebhookRegistrarInterface::class);
        $registrar->expects(self::once())
            ->method('deregister')
            ->with($source)
            ->willReturn(false)
        ;
        $registry = $this->createMock(WebhookRegistrarRegistryInterface::class);
        $registry->expects(self::once())
            ->method('hasProvider')
            ->with('codeberg')
            ->willReturn(true)
        ;
        $registry->expects(self::once())
            ->method('forProvider')
            ->with('codeberg')
            ->willReturn($registrar)
        ;

        $this->sourceRepository->expects(self::once())
            ->method('findByIdInOrganization')
            ->with('source-1', 'org-1')
            ->willReturn($source)
        ;
        $this->sourceRepository->expects(self::once())
            ->method('findById')
            ->with('source-1')
            ->willReturn($source)
        ;
        $this->sourceRepository->expects(self::once())
            ->method('deleteAndFlush')
            ->with($source)
        ;

        $result = $this->commandService(
            webhookAutoRegistrationService: new WebhookAutoRegistrationService($registry, $this->sourceRepository, $this->inertSourceAuthorizationInvalidationService()),
        )->deleteSourceAfterWebhookCleanup('source-1', 'org-1');

        self::assertTrue($result['source_found']);
        self::assertTrue($result['deregistration_required']);
        self::assertFalse($result['deregistered']);
        self::assertTrue($result['deleted']);
    }

    private function commandService(
        ?SourceProviderInstanceRepositoryInterface $sourceProviderInstanceRepository = null,
        ?WebhookAutoRegistrationService $webhookAutoRegistrationService = null,
    ): SourceCommandService {
        return new SourceCommandService(
            $this->sourceRepository,
            $this->providerAuthorizationRepository,
            $this->createMock(SourceProviderRegistryInterface::class),
            new ProviderAuthorizationFactory(new ProviderAuthorizationSecrets($this->secretEncryptor)),
            new ProviderAuthorizationSecrets($this->secretEncryptor),
            new SourceAuthorizationStrategyResolver(),
            new SourceMaintenanceService($this->sourceRepository),
            $this->sourceOAuthAccountOwnershipVerifier,
            $sourceProviderInstanceRepository ?? $this->createMock(SourceProviderInstanceRepositoryInterface::class),
            $this->inertSourceAuthorizationInvalidationService(),
            $webhookAutoRegistrationService ?? $this->inertWebhookAutoRegistrationService(),
            new SourceWebhookSecrets(new SodiumSecretEncryptor('test-webhook-key')),
        );
    }
}
