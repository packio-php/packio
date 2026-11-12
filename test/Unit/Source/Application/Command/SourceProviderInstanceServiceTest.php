<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Command;

use Common\Branding\BrandingConfig;
use Common\Security\SecretEncryptorInterface;
use Packio\Source\Api\Exception\SourceProviderInstanceException;
use Packio\Source\Application\Command\SourceProviderInstanceService;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Security\SourceProviderInstanceSecrets;
use Packio\Source\Application\Service\SourceProviderInstanceOAuthConfig;
use Packio\Source\Domain\Dto\SourceProviderInstanceCleanupResult;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceCleanupRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

#[CoversClass(SourceProviderInstanceService::class)]
#[CoversClass(SourceProviderInstanceSecrets::class)]
#[CoversClass(SourceProviderInstance::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceProviderInstanceServiceTest extends TestCase
{
    use InertSourceCollaborators;

    /** @var MockObject&SourceProviderInstanceRepositoryInterface */
    private MockObject $sourceProviderInstanceRepository;

    /** @var MockObject&SecretEncryptorInterface */
    private MockObject $secretEncryptor;

    /** @var MockObject&SourceRepositoryInterface */
    private MockObject $sourceRepository;

    /** @var MockObject&ProviderAuthorizationRepositoryInterface */
    private MockObject $providerAuthorizationRepository;

    /** @var MockObject&SourceProviderInstanceCleanupRepositoryInterface */
    private MockObject $sourceProviderInstanceCleanupRepository;

    /** @var MockObject&OrganizationQueryPort */
    private MockObject $organizationQueryPort;

    /** @var MockObject&OAuthProviderPort */
    private MockObject $oauthProviderPort;

    protected function setUp(): void
    {
        $this->sourceProviderInstanceRepository        = $this->createMock(SourceProviderInstanceRepositoryInterface::class);
        $this->secretEncryptor                         = $this->createMock(SecretEncryptorInterface::class);
        $this->sourceRepository                        = $this->createMock(SourceRepositoryInterface::class);
        $this->providerAuthorizationRepository         = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $this->sourceProviderInstanceCleanupRepository = $this->createMock(SourceProviderInstanceCleanupRepositoryInterface::class);
        $this->organizationQueryPort                   = $this->createMock(OrganizationQueryPort::class);
        $this->oauthProviderPort                       = $this->createMock(OAuthProviderPort::class);
    }

    #[Test]
    public function createNormalizesBaseUrlAndEncryptsClientSecret(): void
    {
        $this->sourceProviderInstanceRepository->expects(self::once())
            ->method('findByOrganizationProviderAndBaseUrl')
            ->with('org-1', SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED, 'https://gitlab.company.test')
            ->willReturn(null)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('encrypt')
            ->with('client-secret')
            ->willReturn('encrypted-client-secret')
        ;
        $this->sourceProviderInstanceRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn (SourceProviderInstance $sourceProviderInstance): bool => 'org-1' === $sourceProviderInstance->organizationId()
                    && SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED === $sourceProviderInstance->providerType()
                    && 'Company GitLab' === $sourceProviderInstance->label()
                    && 'https://gitlab.company.test' === $sourceProviderInstance->baseUrl()
                    && 'client-id' === $sourceProviderInstance->clientId()
                    && 'encrypted-client-secret' === $sourceProviderInstance->storedClientSecret()
                    && SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN === $sourceProviderInstance->authMode()
                    && 'user-1' === $sourceProviderInstance->createdByUserId(),
            ))
            ->willReturnArgument(0)
        ;

        $instance = $this->createService()->create(
            organizationId: 'org-1',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: ' Company GitLab ',
            baseUrl: 'https://GITLAB.COMPANY.test/',
            clientId: ' client-id ',
            clientSecret: 'client-secret',
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
            createdByUserId: 'user-1',
        );

        self::assertSame('https://gitlab.company.test', $instance->baseUrl());
    }

    #[Test]
    public function createRejectsNonOriginBaseUrl(): void
    {
        $this->sourceProviderInstanceRepository->expects(self::never())->method('save');
        $this->secretEncryptor->expects(self::never())->method('encrypt');

        $this->expectException(SourceProviderInstanceException::class);
        $this->expectExceptionMessage('base URL must be an HTTPS origin URL');

        $this->createService()->create(
            organizationId: 'org-1',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: 'https://gitlab.company.test/root/project',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );
    }

    #[Test]
    public function createRejectsDuplicateInstanceInOrganization(): void
    {
        $sourceProviderInstance = SourceProviderInstance::create(
            organizationId: 'org-1',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Existing',
            baseUrl: 'https://gitlab.company.test',
            clientId: 'client-id',
            clientSecret: null,
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2,
        );

        $this->sourceProviderInstanceRepository->expects(self::once())
            ->method('findByOrganizationProviderAndBaseUrl')
            ->with('org-1', SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED, 'https://gitlab.company.test')
            ->willReturn($sourceProviderInstance)
        ;
        $this->sourceProviderInstanceRepository->expects(self::never())->method('save');

        $this->expectException(SourceProviderInstanceException::class);
        $this->expectExceptionMessage('already exists');

        $this->createService()->create(
            organizationId: 'org-1',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: 'https://gitlab.company.test',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );
    }

    #[Test]
    public function createRejectsOAuthCapableInstanceWithoutClientCredentials(): void
    {
        $this->sourceProviderInstanceRepository->expects(self::never())->method('findByOrganizationProviderAndBaseUrl');
        $this->sourceProviderInstanceRepository->expects(self::never())->method('save');
        $this->secretEncryptor->expects(self::never())->method('encrypt');

        $this->expectException(SourceProviderInstanceException::class);
        $this->expectExceptionMessage('OAuth client ID and client secret are required');

        $this->createService()->create(
            organizationId: 'org-1',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: 'https://gitlab.company.test',
            clientId: 'client-id',
            clientSecret: null,
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
        );
    }

    #[Test]
    public function deleteRejectsInstanceWithSourceDependencies(): void
    {
        SourceProviderInstance::create(
            organizationId: 'org-1',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: 'https://gitlab.company.test',
            clientId: 'client-id',
            clientSecret: null,
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
        );
        $sourceDependency = Source::register('org-1', 'gitlab_self_hosted', 'https://gitlab.company.test/group/repo', 'Repository', 'main');
        $sourceDependency->setIdentifier(Uuid::uuid7());

        $this->sourceProviderInstanceCleanupRepository->expects(self::once())
            ->method('deleteStrict')
            ->with('org-1', 'instance-1')
            ->willThrowException(SourceProviderInstanceException::inUse())
        ;

        $this->expectException(SourceProviderInstanceException::class);
        $this->expectExceptionMessage('cannot be deleted');

        $this->createService()->delete('org-1', 'instance-1');
    }

    #[Test]
    public function deleteRejectsInstanceWithInactiveAuthorizationDependencies(): void
    {
        SourceProviderInstance::create(
            organizationId: 'org-1',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: 'https://gitlab.company.test',
            clientId: 'client-id',
            clientSecret: null,
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
        );
        $providerAuthorization = ProviderAuthorization::authorize(
            'org-1',
            SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            'oauth',
            [],
            'Former administrator',
        );
        $providerAuthorization->assignProviderInstance('instance-1');
        $providerAuthorization->updateStatus('inactive');
        $providerAuthorization->setIdentifier(Uuid::uuid7());

        $this->sourceProviderInstanceCleanupRepository->expects(self::once())
            ->method('deleteStrict')
            ->with('org-1', 'instance-1')
            ->willThrowException(SourceProviderInstanceException::inUse())
        ;

        $this->expectException(SourceProviderInstanceException::class);
        $this->expectExceptionMessage('cannot be deleted');

        $this->createService()->delete('org-1', 'instance-1');
    }

    #[Test]
    public function deleteDelegatesStrictCleanupToRepository(): void
    {
        SourceProviderInstance::create(
            organizationId: 'org-1',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: 'https://gitlab.company.test',
            clientId: 'client-id',
            clientSecret: null,
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
        );
        $this->sourceProviderInstanceCleanupRepository->expects(self::once())
            ->method('deleteStrict')
            ->with('org-1', 'instance-1')
        ;

        $this->createService()->delete('org-1', 'instance-1');
    }

    #[Test]
    public function dependenciesSummarizeInactiveAuthorizationAndSource(): void
    {
        $sourceProviderInstance = SourceProviderInstance::create('org-1', SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED, 'Company GitLab', 'https://gitlab.company.test', 'client-id', null, SourceProviderInstance::AUTH_MODE_OAUTH2);
        $sourceProviderInstance->setIdentifier(Uuid::fromString('018f7f2a-7d5f-7a43-a785-0a0a0a0a0a01'));
        $providerAuthorization = ProviderAuthorization::authorize('org-1', 'gitlab_self_hosted', 'oauth', [], 'Former administrator');
        $providerAuthorization->setIdentifier(Uuid::fromString('018f7f2a-7d5f-7a43-a785-0a0a0a0a0a02'));
        $providerAuthorization->assignProviderInstance('instance-1');
        $providerAuthorization->updateStatus('inactive');
        $source = Source::register('org-1', 'gitlab_self_hosted', 'https://gitlab.company.test/group/repo', 'Repository', 'main');
        $source->setIdentifier(Uuid::fromString('018f7f2a-7d5f-7a43-a785-0a0a0a0a0a03'));
        $source->assignProviderInstance('instance-1');
        $source->assignProviderAuthorization($providerAuthorization->getIdentifier()->toString());

        $this->sourceProviderInstanceRepository->method('findByIdInOrganization')->with('instance-1', 'org-1')->willReturn($sourceProviderInstance);
        $this->providerAuthorizationRepository->method('findByProviderInstance')->with('instance-1', 'org-1')->willReturn([$providerAuthorization]);
        $this->sourceRepository->method('findByProviderInstance')->willReturn([$source]);
        $this->sourceRepository->method('findByProviderAuthorizationIds')->willReturn([$source]);

        $dependencies = $this->createService()->dependencies('org-1', 'instance-1');

        self::assertSame(1, $dependencies->sourceCount());
        self::assertSame(1, $dependencies->inactiveAuthorizationCount());
        self::assertSame('inactive', $dependencies->authorizations[0]['status']);
    }

    #[Test]
    public function shortcutCleanupLogsSuccessfulInactiveAuthorizationDeletion(): void
    {
        $sourceProviderInstance = SourceProviderInstance::create('org-1', SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED, 'Company GitLab', 'https://gitlab.company.test', 'client-id', null, SourceProviderInstance::AUTH_MODE_OAUTH2);
        $this->sourceProviderInstanceRepository->method('findByIdInOrganization')->willReturn($sourceProviderInstance);
        $this->providerAuthorizationRepository->method('findByProviderInstance')->willReturn([]);
        $this->sourceRepository->method('findByProviderInstance')->willReturn([]);
        $this->sourceRepository->method('findByProviderAuthorizationIds')->willReturn([]);
        $this->sourceProviderInstanceCleanupRepository->expects(self::once())->method('deleteWithInactiveAuthorizations')->willReturn(new SourceProviderInstanceCleanupResult(1, 2));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Source provider instance and inactive authorizations deleted.', self::callback(static fn (array $context): bool => 1 === $context['deleted_inactive_authorizations_count'] && 2 === $context['deleted_source_oauth_accounts_count']));

        $sourceProviderInstanceService        = new SourceProviderInstanceService($this->sourceProviderInstanceRepository, new SourceProviderInstanceSecrets($this->secretEncryptor), $this->sourceRepository, $this->providerAuthorizationRepository, $this->organizationQueryPort, $this->oauthProviderPort, $this->inertSourceProviderInstanceOAuthConfig(), $this->sourceProviderInstanceCleanupRepository, $logger);
        $sourceProviderInstanceCleanupResult  = $sourceProviderInstanceService->deleteWithInactiveAuthorizations('org-1', 'instance-1');

        self::assertSame(1, $sourceProviderInstanceCleanupResult->deletedInactiveAuthorizationsCount);
    }

    #[Test]
    public function shortcutCleanupLogsAndRejectsSourceDependenciesBeforeDeleting(): void
    {
        $sourceProviderInstance = SourceProviderInstance::create('org-1', SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED, 'Company GitLab', 'https://gitlab.company.test', 'client-id', null, SourceProviderInstance::AUTH_MODE_OAUTH2);
        $source                 = Source::register('org-1', 'gitlab_self_hosted', 'https://gitlab.company.test/group/repo', 'Repository', 'main');
        $source->setIdentifier(Uuid::uuid7());
        $this->sourceProviderInstanceRepository->method('findByIdInOrganization')->willReturn($sourceProviderInstance);
        $this->providerAuthorizationRepository->method('findByProviderInstance')->willReturn([]);
        $this->sourceRepository->method('findByProviderInstance')->willReturn([$source]);
        $this->sourceRepository->method('findByProviderAuthorizationIds')->willReturn([]);
        $this->sourceProviderInstanceCleanupRepository->expects(self::never())->method('deleteWithInactiveAuthorizations');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('Source provider instance cleanup rejected due to dependencies.', self::callback(static fn (array $context): bool => 1 === $context['source_count'] && 0 === $context['active_authorization_count']));

        $sourceProviderInstanceService = new SourceProviderInstanceService($this->sourceProviderInstanceRepository, new SourceProviderInstanceSecrets($this->secretEncryptor), $this->sourceRepository, $this->providerAuthorizationRepository, $this->organizationQueryPort, $this->oauthProviderPort, $this->inertSourceProviderInstanceOAuthConfig(), $this->sourceProviderInstanceCleanupRepository, $logger);
        $this->expectException(SourceProviderInstanceException::class);
        $sourceProviderInstanceService->deleteWithInactiveAuthorizations('org-1', 'instance-1');
    }

    #[Test]
    public function authorizationUrlForInstanceUsesPersistedInstanceConfig(): void
    {
        $sourceProviderInstance = SourceProviderInstance::create(
            organizationId: 'org-1',
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: 'https://gitlab.company.test',
            clientId: 'client-id',
            clientSecret: 'encrypted-client-secret',
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
        );

        $this->organizationQueryPort->expects(self::once())
            ->method('resolveOrganizationId')
            ->with('my-org')
            ->willReturn('org-1')
        ;
        $this->sourceProviderInstanceRepository->expects(self::once())
            ->method('findActiveByIdInOrganization')
            ->with('instance-1', 'org-1')
            ->willReturn($sourceProviderInstance)
        ;
        $this->secretEncryptor->expects(self::once())
            ->method('decrypt')
            ->with('encrypted-client-secret')
            ->willReturn('client-secret')
        ;
        $this->oauthProviderPort->expects(self::once())
            ->method('getAuthorizationUrlForProviderInstance')
            ->with(
                SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                self::callback(static fn (array $config): bool => 'https://gitlab.company.test' === $config['base_url']
                    && 'client-id' === $config['client_id']
                    && 'client-secret' === $config['client_secret']
                    && 'https://packio.test/auth/oauth/gitlab_self_hosted/source-callback' === $config['redirect_url']),
                'state-1',
                ['read_user', 'api', 'read_repository'],
                null,
            )
            ->willReturn('https://gitlab.company.test/oauth/authorize?...')
        ;

        $sourceProviderInstanceService = new SourceProviderInstanceService(
            $this->sourceProviderInstanceRepository,
            new SourceProviderInstanceSecrets($this->secretEncryptor),
            $this->sourceRepository,
            $this->providerAuthorizationRepository,
            $this->organizationQueryPort,
            $this->oauthProviderPort,
            new SourceProviderInstanceOAuthConfig(
                new SourceProviderInstanceSecrets($this->secretEncryptor),
                new BrandingConfig([
                    'app_url' => 'https://packio.test',
                ]),
            ),
            $this->sourceProviderInstanceCleanupRepository,
        );

        self::assertSame(
            'https://gitlab.company.test/oauth/authorize?...',
            $sourceProviderInstanceService->authorizationUrlForInstance(
                'my-org',
                SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
                'instance-1',
                'state-1',
            ),
        );
    }

    #[Test]
    public function baseUrlRejectsCredentialsBeforeTheyCanBeDiscardedByNormalization(): void
    {
        $this->expectException(SourceProviderInstanceException::class);
        $this->createService()->normalizeBaseUrl('https://user:secret@gitlab.company.test');
    }

    #[Test]
    public function baseUrlRejectsUnapprovedPort(): void
    {
        $this->expectException(SourceProviderInstanceException::class);
        $this->createService()->normalizeBaseUrl('https://gitlab.company.test:8443');
    }

    private function createService(): SourceProviderInstanceService
    {
        return new SourceProviderInstanceService(
            $this->sourceProviderInstanceRepository,
            new SourceProviderInstanceSecrets($this->secretEncryptor),
            $this->sourceRepository,
            $this->providerAuthorizationRepository,
            $this->organizationQueryPort,
            $this->oauthProviderPort,
            $this->inertSourceProviderInstanceOAuthConfig(),
            $this->sourceProviderInstanceCleanupRepository,
        );
    }
}
