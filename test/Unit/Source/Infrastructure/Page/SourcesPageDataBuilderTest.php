<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Page;

use Common\Branding\BrandingConfig;
use Common\Provider\SourceProviderMetadataCatalog;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceProviderDto;
use Packio\Source\Api\Dto\SourceProviderInstanceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;
use Packio\Source\Application\Provider\SourceProviderLabelResolver;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Infrastructure\Page\SourceIndexFilterCriteria;
use Packio\Source\Infrastructure\Page\SourcesPageDataBuilder;
use Packio\Webhook\Api\Dto\WebhookEventDto;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use PackioTest\Support\Fake\FallbackAvailabilityCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourcesPageDataBuilder::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourcesPageDataBuilderTest extends TestCase
{
    use FallbackAvailabilityCollaborators;

    private const string ORG_ID   = 'org-01';
    private const string ORG_SLUG = 'acme';
    private const string ORG_NAME = 'Acme Corp';

    private BrandingConfig $brandingConfig;

    /** @var MockObject&SourceApiFacadeInterface */
    private SourceApiFacadeInterface $sourceApiFacade;

    /** @var MockObject&SourceSecretResolverInterface */
    private SourceSecretResolverInterface $sourceSecretResolver;

    /** @var MockObject&WebhookApiFacadeInterface */
    private WebhookApiFacadeInterface $webhookApiFacade;

    private OAuthProviderAvailabilityResolver $oAuthProviderAvailabilityResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brandingConfig = new BrandingConfig([
            'domain'                    => 'packio.us',
            'registry_base_domain'      => 'packio.us',
            'app_url'                   => 'https://app.packio.us',
            'composer_repository_alias' => 'packio',
            'short_name'                => 'packio',
            'product_name'              => 'Packio',
        ]);

        $this->sourceApiFacade                   = $this->createMock(SourceApiFacadeInterface::class);
        $this->sourceSecretResolver              = $this->createMock(SourceSecretResolverInterface::class);
        $this->webhookApiFacade                  = $this->createMock(WebhookApiFacadeInterface::class);
        $this->oAuthProviderAvailabilityResolver = new OAuthProviderAvailabilityResolver([
            'github' => [
                'enabled' => true,
                'source'  => [
                    'enabled'       => true,
                    'client_id'     => 'github-source-client',
                    'client_secret' => 'github-source-secret',
                ],
            ],
            'gitlab' => [
                'enabled' => false,
            ],
        ], new SourceProviderMetadataCatalog(), $this->fallbackProjectSettings());
    }

    public function testBuildReturnsExpectedStructureForOwner(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createOwnerMembership();

        $providerRegistry = $this->createMock(SourceProviderRegistryInterface::class);
        $providerRegistry->method('label')->willReturnMap([['github', 'GitHub']]);
        $providerRegistry
            ->method('providers')
            ->willReturn([])
        ;
        $sourceProviderLabelResolver = new SourceProviderLabelResolver($providerRegistry);

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('sourcesForOrganization')
            ->willReturn($this->sampleSources())
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('availableProviders')
            ->willReturn($this->sampleProviders())
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('providerAuthorizations')
            ->with(self::ORG_ID)
            ->willReturn($this->sampleAuthorizations())
        ;

        $this->webhookApiFacade
            ->expects(self::never())
            ->method('recentEvents')
        ;

        $this->webhookApiFacade
            ->expects(self::once())
            ->method('recentEventsForSources')
            ->with(['source-01'], 3)
            ->willReturn([
                'source-01' => [
                    new WebhookEventDto(
                        sourceId: 'source-01',
                        provider: 'github',
                        deliveryId: 'del-001',
                        event: 'push',
                        status: 'delivered',
                        message: 'OK',
                        createdAt: '2024-06-01T00:00:00+00:00',
                        processedAt: '2024-06-01T00:00:01+00:00',
                    ),
                ],
            ])
        ;

        $this->sourceSecretResolver
            ->expects(self::once())
            ->method('resolveWebhookSecret')
            ->with('source-01', self::ORG_ID)
            ->willReturn('whsec_abc123')
        ;

        $result = $this->createBuilder($sourceProviderLabelResolver)->build($organizationDto, $organizationMemberDto);

        // can_manage_sources
        self::assertTrue($result['can_manage_sources']);

        // providers
        self::assertCount(2, $result['providers']);
        self::assertSame('github', $result['providers'][0]['key']);
        self::assertTrue($result['providers'][0]['oauth_enabled']);
        self::assertTrue($result['providers'][0]['oauth_available']);
        self::assertSame('gitlab', $result['providers'][1]['key']);
        self::assertFalse($result['providers'][1]['oauth_enabled']);
        self::assertFalse($result['providers'][1]['oauth_available']);

        // provider_authorizations
        self::assertCount(1, $result['provider_authorizations']);
        $auth = $result['provider_authorizations'][0];
        self::assertSame('auth-01', $auth['id']);
        self::assertSame('github', $auth['provider']);
        self::assertSame('github_token', $auth['auth_strategy']);
        self::assertSame('github-oauth-id', $auth['source_oauth_account_id']);
        self::assertSame('healthy', $auth['auth_health']);
        self::assertSame('2024-06-01T00:00:00+00:00', $auth['last_token_refresh_at']);

        // sources
        self::assertCount(1, $result['sources']);

        $source = $result['sources'][0];
        self::assertSame('source-01', $source['id']);
        self::assertSame('My Repo', $source['name']);
        self::assertSame('github', $source['provider']);
        self::assertSame('GitHub', $source['provider_label']);
        self::assertSame('https://github.com/acme/repo', $source['url']);
        self::assertSame('https://app.packio.us/webhooks/github/source-01', $source['webhook_url']);
        self::assertSame('whsec_abc123', $source['webhook_secret']);
        self::assertSame([
            [
                'delivery_id'      => 'del-001',
                'provider'         => 'github',
                'event'            => 'push',
                'status'           => 'delivered',
                'message'          => 'OK',
                'created_at'       => '2024-06-01T00:00:00+00:00',
                'processed_at'     => '2024-06-01T00:00:01+00:00',
                'scheduled_job_id' => null,
                'reprocess_count'  => 0,
            ],
        ], $source['webhook_events']);
    }

    public function testBuildMarksOAuthUnavailableWhenSourceClientIsMissing(): void
    {
        $this->oAuthProviderAvailabilityResolver = new OAuthProviderAvailabilityResolver([
            'github' => [
                'enabled' => true,
                'source'  => [
                    'enabled'       => true,
                    'client_id'     => '',
                    'client_secret' => '',
                ],
            ],
        ], new SourceProviderMetadataCatalog(), $this->fallbackProjectSettings());

        $providerRegistry = $this->createMock(SourceProviderRegistryInterface::class);
        $providerRegistry
            ->method('providers')
            ->willReturn([])
        ;
        $sourceProviderLabelResolver = new SourceProviderLabelResolver($providerRegistry);

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('sourcesForOrganization')
            ->willReturn([])
        ;
        $this->sourceApiFacade
            ->expects(self::once())
            ->method('availableProviders')
            ->willReturn([
                new SourceProviderDto(
                    key: 'github',
                    name: 'GitHub',
                    description: '',
                    capabilities: [
                        'oauth' => true,
                    ],
                    oauthProvider: 'github',
                    oauthEnabled: true,
                    oauthAvailable: true,
                ),
            ])
        ;
        $this->sourceApiFacade
            ->expects(self::once())
            ->method('providerAuthorizations')
            ->willReturn([])
        ;
        $this->webhookApiFacade
            ->expects(self::never())
            ->method('recentEvents')
        ;

        $result = $this->createBuilder($sourceProviderLabelResolver)->build($this->createOrganization(), $this->createOwnerMembership());

        self::assertFalse($result['providers'][0]['oauth_enabled']);
        self::assertFalse($result['providers'][0]['oauth_available']);
    }

    public function testNonOwnerDoesNotReceiveWebhookSecret(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createMemberMembership();

        $providerRegistry = $this->createMock(SourceProviderRegistryInterface::class);
        $providerRegistry
            ->method('providers')
            ->willReturn([])
        ;
        $sourceProviderLabelResolver = new SourceProviderLabelResolver($providerRegistry);

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('sourcesForOrganization')
            ->willReturn($this->sampleSources())
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('availableProviders')
            ->willReturn($this->sampleProviders())
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('providerAuthorizations')
            ->willReturn($this->sampleAuthorizations())
        ;

        $this->webhookApiFacade
            ->method('recentEventsForSources')
            ->willReturn([])
        ;

        $this->sourceSecretResolver
            ->expects(self::never())
            ->method('resolveWebhookSecret')
        ;

        $result = $this->createBuilder($sourceProviderLabelResolver)->build($organizationDto, $organizationMemberDto);

        self::assertFalse($result['can_manage_sources']);
        self::assertArrayNotHasKey('webhook_secret', $result['sources'][0]);
        self::assertSame('', $result['provider_authorizations'][0]['masked_value']);
    }

    public function testSourceNameUsesOwnerRepositoryFallback(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createOwnerMembership();

        $providerRegistry = $this->createMock(SourceProviderRegistryInterface::class);
        $providerRegistry
            ->method('providers')
            ->willReturn([])
        ;
        $sourceProviderLabelResolver = new SourceProviderLabelResolver($providerRegistry);

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('sourcesForOrganization')
            ->willReturn([
                new SourceDto(
                    id: 'source-no-display',
                    provider: 'github',
                    type: 'github',
                    displayName: '',
                    canonicalUrl: 'https://github.com/acme/repo',
                    url: 'https://github.com/acme/repo',
                    owner: 'acme',
                    repository: 'repo',
                    defaultRef: 'main',
                    organizationId: self::ORG_ID,
                ),
            ])
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('availableProviders')
            ->willReturn([])
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('providerAuthorizations')
            ->willReturn([])
        ;

        $this->webhookApiFacade
            ->method('recentEventsForSources')
            ->willReturn([])
        ;

        $this->sourceSecretResolver
            ->method('resolveWebhookSecret')
            ->willReturn(null)
        ;

        $result = $this->createBuilder($sourceProviderLabelResolver)->build($organizationDto, $organizationMemberDto);

        self::assertSame('acme/repo', $result['sources'][0]['name']);
    }

    public function testArchivedSourceIncludedInUnifiedList(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createOwnerMembership();

        $providerRegistry = $this->createMock(SourceProviderRegistryInterface::class);
        $providerRegistry
            ->method('providers')
            ->willReturn([])
        ;
        $sourceProviderLabelResolver = new SourceProviderLabelResolver($providerRegistry);

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('sourcesForOrganization')
            ->willReturn([
                new SourceDto(
                    id: 'source-active',
                    provider: 'github',
                    type: 'github',
                    displayName: 'Active',
                    canonicalUrl: 'https://github.com/acme/active',
                    url: 'https://github.com/acme/active',
                    owner: 'acme',
                    repository: 'active',
                    defaultRef: 'main',
                    organizationId: self::ORG_ID,
                    archived: false,
                ),
                new SourceDto(
                    id: 'source-archived',
                    provider: 'gitlab',
                    type: 'gitlab',
                    displayName: 'Archived',
                    canonicalUrl: 'https://gitlab.com/acme/archived',
                    url: 'https://gitlab.com/acme/archived',
                    owner: 'acme',
                    repository: 'archived',
                    defaultRef: 'main',
                    organizationId: self::ORG_ID,
                    archived: true,
                    archivedAt: '2024-05-01T00:00:00+00:00',
                ),
            ])
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('availableProviders')
            ->willReturn([])
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('providerAuthorizations')
            ->willReturn([])
        ;

        $this->webhookApiFacade
            ->method('recentEventsForSources')
            ->willReturn([])
        ;

        $this->sourceSecretResolver
            ->method('resolveWebhookSecret')
            ->willReturn(null)
        ;

        $result = $this->createBuilder($sourceProviderLabelResolver)->build($organizationDto, $organizationMemberDto);

        self::assertCount(2, $result['sources']);
        self::assertSame('source-active', $result['sources'][0]['id']);
        self::assertSame('source-archived', $result['sources'][1]['id']);
        self::assertTrue($result['sources'][1]['archived']);
        self::assertSame('2024-05-01T00:00:00+00:00', $result['sources'][1]['archived_at']);
    }

    public function testBuildPaginatesSourcesAndKeepsTotalStats(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createOwnerMembership();
        $sources               = [];

        for ($index = 1; $index <= 25; ++$index) {
            $archived  = $index > 20;
            $sources[] = new SourceDto(
                id: 'source-' . $index,
                provider: 'github',
                type: 'github',
                displayName: 'Source ' . $index,
                canonicalUrl: 'https://github.com/acme/source-' . $index,
                url: 'https://github.com/acme/source-' . $index,
                owner: 'acme',
                repository: 'source-' . $index,
                defaultRef: 'main',
                organizationId: self::ORG_ID,
                archived: $archived,
                archivedAt: $archived ? '2024-05-01T00:00:00+00:00' : '',
            );
        }

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('sourcesForOrganization')
            ->willReturn($sources)
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('availableProviders')
            ->willReturn([])
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('providerAuthorizations')
            ->willReturn([])
        ;

        $this->webhookApiFacade
            ->method('recentEventsForSources')
            ->willReturn([])
        ;

        $this->sourceSecretResolver
            ->method('resolveWebhookSecret')
            ->willReturn(null)
        ;

        $result = $this->createBuilder()->build(
            $organizationDto,
            $organizationMemberDto,
            new PaginationCriteria(page: 2, perPage: 18),
        );

        self::assertCount(7, $result['sources']);
        self::assertSame('source-19', $result['sources'][0]['id']);
        self::assertSame([], $result['sources'][0]['webhook_events']);
        self::assertSame('source-21', $result['sources'][2]['id']);
        self::assertSame(25, $result['source_stats']['total']);
        self::assertSame(20, $result['source_stats']['active']);
        self::assertSame(5, $result['source_stats']['archived']);
        self::assertCount(25, $result['source_urls']);
        self::assertSame(25, $result['pagination']['total']);
        self::assertSame(2, $result['pagination']['current_page']);
        self::assertSame(2, $result['pagination']['last_page']);
        self::assertSame(19, $result['pagination']['from']);
        self::assertSame(25, $result['pagination']['to']);
        self::assertArrayNotHasKey('items', $result['pagination']);
    }

    public function testBuildFiltersSourcesBeforePaginationAndKeepsGlobalStats(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createOwnerMembership();

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('sourcesForOrganization')
            ->willReturn([
                new SourceDto(
                    id: 'source-alpha',
                    provider: 'github',
                    type: 'github',
                    displayName: 'Alpha',
                    canonicalUrl: 'https://github.com/acme/alpha',
                    url: 'https://github.com/acme/alpha',
                    owner: 'acme',
                    repository: 'alpha',
                    defaultRef: 'main',
                    organizationId: self::ORG_ID,
                    visibility: 'private',
                    active: true,
                    lastSyncStatus: 'success',
                ),
                new SourceDto(
                    id: 'source-legacy',
                    provider: 'gitlab',
                    type: 'gitlab',
                    displayName: 'Legacy Source',
                    canonicalUrl: 'https://gitlab.com/acme/legacy',
                    url: 'https://gitlab.com/acme/legacy',
                    owner: 'acme',
                    repository: 'legacy',
                    defaultRef: 'main',
                    organizationId: self::ORG_ID,
                    visibility: 'public',
                    active: false,
                    lastSyncStatus: 'failed',
                    archived: true,
                    archivedAt: '2024-05-01T00:00:00+00:00',
                ),
                new SourceDto(
                    id: 'source-beta',
                    provider: 'generic_git',
                    type: 'generic_git',
                    displayName: 'Beta',
                    canonicalUrl: 'https://example.com/acme/beta.git',
                    url: 'https://example.com/acme/beta.git',
                    owner: 'acme',
                    repository: 'beta',
                    defaultRef: 'develop',
                    organizationId: self::ORG_ID,
                    visibility: 'public',
                    active: false,
                    lastSyncStatus: 'pending',
                ),
            ])
        ;
        $this->sourceApiFacade
            ->expects(self::once())
            ->method('availableProviders')
            ->willReturn([])
        ;
        $this->sourceApiFacade
            ->expects(self::once())
            ->method('providerAuthorizations')
            ->willReturn([])
        ;
        $this->webhookApiFacade
            ->method('recentEventsForSources')
            ->willReturn([])
        ;
        $this->sourceSecretResolver
            ->method('resolveWebhookSecret')
            ->willReturn(null)
        ;

        $result = $this->createBuilder()->build(
            $organizationDto,
            $organizationMemberDto,
            new PaginationCriteria(page: 1, perPage: 18),
            SourceIndexFilterCriteria::fromArray([
                'search'      => 'legacy',
                'provider'    => ['gitlab'],
                'visibility'  => ['public'],
                'status'      => ['archived'],
                'sync_status' => ['failed'],
            ]),
        );

        self::assertCount(1, $result['sources']);
        self::assertSame('source-legacy', $result['sources'][0]['id']);
        self::assertSame(3, $result['source_stats']['total']);
        self::assertSame(2, $result['source_stats']['active']);
        self::assertSame(1, $result['source_stats']['archived']);
        self::assertSame(1, $result['pagination']['total']);
        self::assertSame(['gitlab'], $result['filters']['provider']);
        self::assertSame(['archived'], $result['filters']['status']);
        self::assertSame(['failed'], $result['filters']['sync_status']);
        self::assertSame([
            [
                'value' => 'generic_git',
                'label' => 'Generic Git',
            ],
            [
                'value' => 'github',
                'label' => 'Github',
            ],
            [
                'value' => 'gitlab',
                'label' => 'Gitlab',
            ],
        ], $result['source_filter_options']['providers']);
        self::assertSame([
            [
                'value' => 'failed',
                'label' => 'failed',
            ],
            [
                'value' => 'pending',
                'label' => 'pending',
            ],
            [
                'value' => 'success',
                'label' => 'success',
            ],
        ], $result['source_filter_options']['sync_statuses']);
    }

    public function testGitLabSelfHostedOAuthAvailabilityComesFromProviderInstances(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createOwnerMembership();
        $projectSettings       = $this->createMock(ProjectSettingsApiFacadeInterface::class);
        $projectSettings
            ->method('booleanValue')
            ->willReturnCallback(static fn (string $key, bool $fallback): bool => 'oauth.gitlab_self_hosted.source.enabled' === $key ? true : $fallback)
        ;
        $this->oAuthProviderAvailabilityResolver = new OAuthProviderAvailabilityResolver([], new SourceProviderMetadataCatalog(), $projectSettings);

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('sourcesForOrganization')
            ->willReturn([])
        ;
        $this->sourceApiFacade
            ->expects(self::once())
            ->method('sourceProviderInstances')
            ->with(self::ORG_ID)
            ->willReturn([
                new SourceProviderInstanceDto(
                    id: '00000000-0000-0000-0000-000000000901',
                    organizationId: self::ORG_ID,
                    providerType: 'gitlab_self_hosted',
                    label: 'Company GitLab',
                    baseUrl: 'https://gitlab.company.test',
                    clientId: 'client-id',
                    hasClientSecret: true,
                    authMode: 'oauth2_and_token',
                    status: 'active',
                ),
            ])
        ;
        $this->sourceApiFacade
            ->expects(self::once())
            ->method('availableProviders')
            ->willReturn([
                new SourceProviderDto(
                    key: 'gitlab_self_hosted',
                    name: 'GitLab Self-Hosted',
                    description: 'Self-hosted GitLab provider',
                    capabilities: [
                        'supports_synchronization' => true,
                    ],
                    oauthEnabled: false,
                    oauthAvailable: false,
                ),
            ])
        ;
        $this->sourceApiFacade
            ->expects(self::once())
            ->method('providerAuthorizations')
            ->with(self::ORG_ID)
            ->willReturn([
                new ProviderAuthorizationDto(
                    id: 'auth-01',
                    organizationId: self::ORG_ID,
                    provider: 'gitlab_self_hosted',
                    authStrategy: 'oauth2',
                    status: 'active',
                    accountName: 'gitlab-user',
                    configured: true,
                    maskedValue: '****1234',
                    sourceOAuthAccountId: 'soa-01',
                    providerInstanceId: '00000000-0000-0000-0000-000000000901',
                    authHealth: 'healthy',
                ),
            ])
        ;

        $this->webhookApiFacade
            ->method('recentEventsForSources')
            ->willReturn([])
        ;

        $result = $this->createBuilder()->build($organizationDto, $organizationMemberDto);

        self::assertSame('gitlab_self_hosted', $result['providers'][0]['key']);
        self::assertSame('gitlab_self_hosted', $result['providers'][0]['oauth_provider']);
        self::assertTrue($result['providers'][0]['oauth_enabled']);
        self::assertTrue($result['providers'][0]['oauth_available']);
        self::assertSame('Company GitLab', $result['source_provider_instances'][0]['label']);
        self::assertSame(
            '00000000-0000-0000-0000-000000000901',
            $result['provider_authorizations'][0]['provider_instance_id'],
        );
    }

    /**
     * @return list<SourceDto>
     */
    private function sampleSources(): array
    {
        return [
            new SourceDto(
                id: 'source-01',
                provider: 'github',
                type: 'github',
                displayName: 'My Repo',
                canonicalUrl: 'https://github.com/acme/repo',
                url: 'https://github.com/acme/repo',
                owner: 'acme',
                repository: 'repo',
                defaultRef: 'main',
                organizationId: self::ORG_ID,
                visibility: 'public',
                active: true,
                archived: false,
            ),
        ];
    }

    /**
     * @return list<SourceProviderDto>
     */
    private function sampleProviders(): array
    {
        return [
            new SourceProviderDto(
                key: 'github',
                name: 'GitHub',
                description: 'GitHub provider',
                capabilities: [
                    'webhooks' => true,
                ],
                oauthProvider: 'github',
                oauthEnabled: true,
                oauthAvailable: true,
            ),
            new SourceProviderDto(
                key: 'gitlab',
                name: 'GitLab',
                description: 'GitLab provider',
                capabilities: [
                    'webhooks' => true,
                ],
                oauthProvider: 'gitlab',
                oauthEnabled: false,
                oauthAvailable: false,
            ),
        ];
    }

    /**
     * @return list<ProviderAuthorizationDto>
     */
    private function sampleAuthorizations(): array
    {
        return [
            new ProviderAuthorizationDto(
                id: 'auth-01',
                organizationId: self::ORG_ID,
                provider: 'github',
                authStrategy: 'github_token',
                status: 'configured',
                accountName: 'acme-user',
                configured: true,
                maskedValue: 'github ****abc1',
                sourceOAuthAccountId: 'github-oauth-id',
                authHealth: 'healthy',
                lastTokenRefreshAt: '2024-06-01T00:00:00+00:00',
            ),
        ];
    }

    private function createBuilder(?SourceProviderLabelResolver $sourceProviderLabelResolver = null): SourcesPageDataBuilder
    {
        if (! $sourceProviderLabelResolver instanceof SourceProviderLabelResolver) {
            $registry = $this->createMock(SourceProviderRegistryInterface::class);
            $registry
                ->method('providers')
                ->willReturn([])
            ;
            $sourceProviderLabelResolver = new SourceProviderLabelResolver($registry);
        }

        $oauthApiFacade = $this->createStub(OAuthApiFacadeInterface::class);
        $oauthApiFacade->method('providerAvailabilities')->willReturnCallback($this->oAuthProviderAvailabilityResolver->all(...));
        $oauthApiFacade->method('gitLabSelfHostedSourceConnectEnabled')->willReturnCallback($this->oAuthProviderAvailabilityResolver->gitLabSelfHostedSourceConnectEnabled(...));

        return new SourcesPageDataBuilder(
            $this->sourceApiFacade,
            $this->sourceSecretResolver,
            $this->webhookApiFacade,
            new OrganizationPolicy(),
            $this->brandingConfig,
            $sourceProviderLabelResolver,
            $oauthApiFacade,
        );
    }

    private function createOrganization(): OrganizationDto
    {
        return new OrganizationDto(
            id: self::ORG_ID,
            slug: self::ORG_SLUG,
            name: self::ORG_NAME,
            anonymousAccess: true,
            active: true,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-06-15T00:00:00+00:00',
        );
    }

    private function createOwnerMembership(): OrganizationMemberDto
    {
        return new OrganizationMemberDto(
            id: 'member-01',
            organizationId: self::ORG_ID,
            userId: 'user-01',
            role: 'owner',
        );
    }

    private function createMemberMembership(): OrganizationMemberDto
    {
        return new OrganizationMemberDto(
            id: 'member-02',
            organizationId: self::ORG_ID,
            userId: 'user-02',
            role: 'member',
        );
    }
}
