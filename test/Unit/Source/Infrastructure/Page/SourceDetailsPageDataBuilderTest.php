<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Page;

use Common\Branding\BrandingConfig;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Audit\Api\Dto\AuditEntryDto;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Source\Api\Dto\ProviderAuthorizationDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;
use Packio\Source\Application\Provider\SourceProviderLabelResolver;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Infrastructure\Page\SourceDetailsPageDataBuilder;
use Packio\Webhook\Api\Dto\WebhookEventDto;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function array_keys;

#[CoversClass(SourceDetailsPageDataBuilder::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceDetailsPageDataBuilderTest extends TestCase
{
    private const string ORG_ID   = 'org-01';
    private const string ORG_SLUG = 'acme';
    private const string ORG_NAME = 'Acme Corp';

    private BrandingConfig $brandingConfig;

    /** @var MockObject&PackageApiFacadeInterface */
    private PackageApiFacadeInterface $packageApiFacade;

    /** @var MockObject&ReleaseApiFacadeInterface */
    private ReleaseApiFacadeInterface $releaseApiFacade;

    /** @var MockObject&SourceApiFacadeInterface */
    private SourceApiFacadeInterface $sourceApiFacade;

    /** @var MockObject&SourceSecretResolverInterface */
    private SourceSecretResolverInterface $sourceSecretResolver;

    /** @var MockObject&WebhookApiFacadeInterface */
    private WebhookApiFacadeInterface $webhookApiFacade;

    /** @var AuditApiFacadeInterface&MockObject */
    private AuditApiFacadeInterface $auditApiFacade;

    private SourceProviderLabelResolver $sourceProviderLabelResolver;

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

        $this->packageApiFacade     = $this->createMock(PackageApiFacadeInterface::class);
        $this->releaseApiFacade     = $this->createMock(ReleaseApiFacadeInterface::class);
        $this->sourceApiFacade      = $this->createMock(SourceApiFacadeInterface::class);
        $this->sourceSecretResolver = $this->createMock(SourceSecretResolverInterface::class);
        $this->webhookApiFacade     = $this->createMock(WebhookApiFacadeInterface::class);
        $this->auditApiFacade       = $this->createMock(AuditApiFacadeInterface::class);

        $registry = $this->createMock(SourceProviderRegistryInterface::class);
        $registry
            ->method('providers')
            ->willReturn([])
        ;
        $this->sourceProviderLabelResolver = new SourceProviderLabelResolver($registry);
    }

    public function testBuildReturnsExpectedStructureForOwner(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createOwnerMembership();
        $sourceDto             = $this->createSource(providerAuthorizationId: 'auth-01');

        $this->packageApiFacade
            ->expects(self::once())
            ->method('packagesForOrganization')
            ->willReturn([
                new PackageDto(
                    name: 'acme/pkg-one',
                    type: 'library',
                    description: 'Package one',
                    visibility: 'public',
                    sourceType: 'github',
                    sourceUrl: 'https://github.com/acme/pkg-one',
                    createdAt: '2024-01-01T00:00:00+00:00',
                    updatedAt: '2024-06-01T00:00:00+00:00',
                    organizationId: self::ORG_ID,
                    active: true,
                    sourceId: 'source-01',
                    downloadCount: 10,
                ),
            ])
        ;

        $this->releaseApiFacade
            ->expects(self::once())
            ->method('releasesForPackage')
            ->with('acme/pkg-one', false, self::ORG_ID)
            ->willReturn([
                new ReleaseDto(
                    packageName: 'acme/pkg-one',
                    version: '1.0.0',
                    reference: 'abc123',
                    composer: [],
                    source: [],
                    dist: [],
                    createdAt: '2024-06-01T00:00:00+00:00',
                    organizationId: self::ORG_ID,
                    active: true,
                    downloadCount: 5,
                ),
            ])
        ;

        $this->webhookApiFacade
            ->expects(self::once())
            ->method('recentEvents')
            ->with('source-01', 5)
            ->willReturn([
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
            ])
        ;

        $this->auditApiFacade
            ->expects(self::once())
            ->method('recentBySource')
            ->with(self::ORG_ID, 'source-01', 5)
            ->willReturn([
                new AuditEntryDto(
                    id: 'b6b6ee2b-7eb3-4e5d-bc97-8b2114eafa6e',
                    event: 'source.sync',
                    occurredAt: '2024-06-01T00:00:00+00:00',
                    context: [
                        'status' => 'success',
                    ],
                ),
            ])
        ;

        $this->sourceSecretResolver
            ->expects(self::once())
            ->method('resolveWebhookSecret')
            ->with('source-01', self::ORG_ID)
            ->willReturn('whsec_abc')
        ;

        $this->sourceSecretResolver
            ->expects(self::once())
            ->method('resolveAuthorizationSecret')
            ->with('auth-01')
            ->willReturn('ghp_abcdefgh123456')
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('supportsWebhookAutoRegistration')
            ->with('github')
            ->willReturn(true)
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('findProviderAuthorizationInOrganization')
            ->with('auth-01', self::ORG_ID)
            ->willReturn(
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
            )
        ;

        $result = $this->createBuilder()->build($organizationDto, $organizationMemberDto, $sourceDto);

        // can_manage_sources
        self::assertTrue($result['can_manage_sources']);

        // source
        $sourceData = $result['source'];
        self::assertSame('source-01', $sourceData['id']);
        self::assertSame('Test Repo', $sourceData['name']);
        self::assertSame('github', $sourceData['provider']);
        self::assertTrue($sourceData['packages_active']);
        self::assertSame('whsec_abc', $sourceData['webhook_secret']);
        self::assertSame('ext-001', $sourceData['webhook_external_id']);
        self::assertTrue($sourceData['webhook_registered']);
        self::assertTrue($sourceData['supports_auto_registration']);

        // credentials
        $credentials = $sourceData['credentials'];
        self::assertTrue($credentials['configured']);
        self::assertSame('github_token', $credentials['auth_type']);
        self::assertSame('GitHub authorization configured', $credentials['status_label']);
        self::assertStringContainsString('github_token ****', $credentials['masked_value']);
        self::assertSame('github-oauth-id', $credentials['source_oauth_account_id']);
        self::assertSame('healthy', $credentials['auth_health']);
        self::assertSame('2024-06-01T00:00:00+00:00', $credentials['last_token_refresh_at']);

        // stats
        self::assertSame(1, $result['stats']['packages_count']);
        self::assertSame(1, $result['stats']['releases_count']);
        self::assertSame(1, $result['stats']['deliveries_count']);

        // packages
        self::assertCount(1, $result['packages']);
        self::assertSame('acme/pkg-one', $result['packages'][0]['name']);
        self::assertSame(1, $result['packages'][0]['release_count']);
        self::assertSame('1.0.0', $result['packages'][0]['latest_version']);
        self::assertCount(1, $result['packages'][0]['releases']);

        // latest_package
        self::assertNotNull($result['latest_package']);
        self::assertSame('acme/pkg-one', $result['latest_package']['name']);

        // webhook_events
        self::assertCount(1, $result['webhook_events']);
        self::assertSame('del-001', $result['webhook_events'][0]['delivery_id']);

        // audit
        self::assertCount(1, $result['audit']);
        self::assertSame('b6b6ee2b-7eb3-4e5d-bc97-8b2114eafa6e', $result['audit'][0]['id']);
        self::assertSame('source.sync', $result['audit'][0]['event']);
    }

    public function testMemberDoesNotReceiveWebhookSecret(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createMemberMembership();
        $sourceDto             = $this->createSource(providerAuthorizationId: 'auth-01');

        $this->packageApiFacade
            ->expects(self::once())
            ->method('packagesForOrganization')
            ->willReturn([])
        ;

        $this->webhookApiFacade
            ->expects(self::once())
            ->method('recentEvents')
            ->willReturn([])
        ;

        $this->auditApiFacade
            ->expects(self::once())
            ->method('recentBySource')
            ->willReturn([])
        ;

        $this->sourceSecretResolver
            ->expects(self::never())
            ->method('resolveWebhookSecret')
        ;

        $this->sourceSecretResolver
            ->expects(self::never())
            ->method('resolveAuthorizationSecret')
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('findProviderAuthorizationInOrganization')
            ->willReturn(null)
        ;

        $result = $this->createBuilder()->build($organizationDto, $organizationMemberDto, $sourceDto);

        self::assertFalse($result['can_manage_sources']);
        self::assertArrayNotHasKey('webhook_secret', $result['source']);
        self::assertArrayNotHasKey('webhook_external_id', $result['source']);
        self::assertArrayNotHasKey('webhook_registered', $result['source']);
        self::assertArrayNotHasKey('supports_auto_registration', $result['source']);
        self::assertSame('', $result['source']['credentials']['masked_value']);
    }

    public function testAuditEntriesDoNotExposeContext(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createMemberMembership();
        $sourceDto             = $this->createSource(providerAuthorizationId: 'auth-01');

        $this->packageApiFacade->method('packagesForOrganization')->willReturn([]);
        $this->webhookApiFacade->method('recentEvents')->willReturn([]);
        $this->sourceApiFacade->method('findProviderAuthorizationInOrganization')->willReturn(null);
        $this->sourceSecretResolver->method('resolveAuthorizationSecret')->willReturn('ghp_abcdefgh123456');

        $this->auditApiFacade
            ->method('recentBySource')
            ->willReturn([
                new AuditEntryDto(
                    id: 'b6b6ee2b-7eb3-4e5d-bc97-8b2114eafa6e',
                    event: 'source.sync',
                    occurredAt: '2024-06-01T00:00:00+00:00',
                    context: [
                        'admin_user_id'      => 'x',
                        'skipped_references' => [[
                            'error' => 'fatal',
                        ]],
                    ],
                ),
            ])
        ;

        $result = $this->createBuilder()->build($organizationDto, $organizationMemberDto, $sourceDto);

        self::assertSame(['id', 'event', 'occurred_at'], array_keys($result['audit'][0]));
    }

    public function testCredentialsStatusLabelNotConfigured(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createOwnerMembership();
        $sourceDto             = new SourceDto(
            id: 'source-02',
            provider: 'github',
            type: 'github',
            displayName: 'No Creds',
            canonicalUrl: 'https://github.com/acme/no-creds',
            url: 'https://github.com/acme/no-creds',
            owner: 'acme',
            repository: 'no-creds',
            defaultRef: 'main',
            organizationId: self::ORG_ID,
            credentialsConfigured: false,
        );

        $this->packageApiFacade
            ->expects(self::once())
            ->method('packagesForOrganization')
            ->willReturn([])
        ;

        $this->webhookApiFacade
            ->expects(self::once())
            ->method('recentEvents')
            ->willReturn([])
        ;

        $this->auditApiFacade
            ->expects(self::once())
            ->method('recentBySource')
            ->willReturn([])
        ;

        $this->sourceSecretResolver
            ->expects(self::once())
            ->method('resolveWebhookSecret')
            ->with('source-02', self::ORG_ID)
            ->willReturn(null)
        ;

        $this->sourceSecretResolver
            ->expects(self::never())
            ->method('resolveAuthorizationSecret')
        ;

        $this->sourceApiFacade
            ->expects(self::never())
            ->method('findProviderAuthorizationInOrganization')
        ;

        $result = $this->createBuilder()->build($organizationDto, $organizationMemberDto, $sourceDto);

        $credentials = $result['source']['credentials'];
        self::assertFalse($credentials['configured']);
        self::assertSame('Not configured', $credentials['status_label']);
        self::assertNull($credentials['masked_value']);
    }

    public function testCredentialsMaskUsesPlaceholderWhenOAuthTokenCannotBeResolved(): void
    {
        $organizationDto       = $this->createOrganization();
        $organizationMemberDto = $this->createOwnerMembership();
        $sourceDto             = $this->createSource(providerAuthorizationId: 'auth-01');

        $this->packageApiFacade
            ->expects(self::once())
            ->method('packagesForOrganization')
            ->willReturn([])
        ;

        $this->webhookApiFacade
            ->expects(self::once())
            ->method('recentEvents')
            ->willReturn([])
        ;

        $this->auditApiFacade
            ->expects(self::once())
            ->method('recentBySource')
            ->willReturn([])
        ;

        $this->sourceSecretResolver
            ->method('resolveWebhookSecret')
            ->willReturn('')
        ;

        $this->sourceSecretResolver
            ->expects(self::once())
            ->method('resolveAuthorizationSecret')
            ->with('auth-01')
            ->willThrowException(new SourceTokenRefreshUnavailableException('OAuth account is inactive. Reauthorization required.'))
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('findProviderAuthorizationInOrganization')
            ->willReturn(
                new ProviderAuthorizationDto(
                    id: 'auth-01',
                    organizationId: self::ORG_ID,
                    provider: 'github',
                    authStrategy: 'github_token',
                    status: 'configured',
                    accountName: 'acme-user',
                    configured: true,
                    maskedValue: '',
                    sourceOAuthAccountId: 'github-oauth-id',
                    authHealth: 'reauthorization_required',
                    authFailureReason: 'OAuth account is inactive. Please reconnect your provider.',
                    lastTokenRefreshAt: '2024-06-01T00:00:00+00:00',
                ),
            )
        ;

        $result = $this->createBuilder()->build($organizationDto, $organizationMemberDto, $sourceDto);

        $credentials = $result['source']['credentials'];
        self::assertSame('github_token ••••••••', $credentials['masked_value']);
        self::assertSame('reauthorization_required', $credentials['auth_health']);
        self::assertSame('OAuth account is inactive. Please reconnect your provider.', $credentials['auth_failure_reason']);
    }

    public function testPackagesActiveTrueWhenNoPackages(): void
    {
        $organizationDto = $this->createOrganization();
        $membership      = null;
        $sourceDto       = $this->createSource();

        $this->packageApiFacade
            ->expects(self::once())
            ->method('packagesForOrganization')
            ->willReturn([])
        ;

        $this->webhookApiFacade
            ->expects(self::once())
            ->method('recentEvents')
            ->willReturn([])
        ;

        $this->auditApiFacade
            ->expects(self::once())
            ->method('recentBySource')
            ->willReturn([])
        ;

        $this->sourceSecretResolver
            ->expects(self::never())
            ->method('resolveWebhookSecret')
        ;

        $this->sourceSecretResolver
            ->expects(self::never())
            ->method('resolveAuthorizationSecret')
        ;

        $this->sourceApiFacade
            ->expects(self::never())
            ->method('findProviderAuthorizationInOrganization')
        ;

        $result = $this->createBuilder()->build($organizationDto, $membership, $sourceDto);

        self::assertTrue($result['source']['packages_active']);
    }

    public function testSourceNameUsesOwnerRepositoryFallback(): void
    {
        $organizationDto = $this->createOrganization();
        $membership      = null;
        $sourceDto       = new SourceDto(
            id: 'source-03',
            provider: 'github',
            type: 'github',
            displayName: '',
            canonicalUrl: 'https://github.com/acme/repo',
            url: 'https://github.com/acme/repo',
            owner: 'acme',
            repository: 'repo',
            defaultRef: 'main',
            organizationId: self::ORG_ID,
            providerAuthorizationId: 'auth-01',
            authType: 'github_token',
            credentialsConfigured: true,
        );

        $this->packageApiFacade
            ->expects(self::once())
            ->method('packagesForOrganization')
            ->willReturn([])
        ;

        $this->webhookApiFacade
            ->expects(self::once())
            ->method('recentEvents')
            ->willReturn([])
        ;

        $this->auditApiFacade
            ->expects(self::once())
            ->method('recentBySource')
            ->willReturn([])
        ;

        $this->sourceSecretResolver
            ->expects(self::never())
            ->method('resolveWebhookSecret')
        ;

        $this->sourceSecretResolver
            ->expects(self::never())
            ->method('resolveAuthorizationSecret')
        ;

        $this->sourceApiFacade
            ->expects(self::once())
            ->method('findProviderAuthorizationInOrganization')
            ->willReturn(null)
        ;

        $result = $this->createBuilder()->build($organizationDto, $membership, $sourceDto);

        self::assertSame('acme/repo', $result['source']['name']);
    }

    /**
     * @param array<string, mixed> $extraConfig
     */
    private function createSource(string $providerAuthorizationId = '', array $extraConfig = []): SourceDto
    {
        return new SourceDto(
            id: 'source-01',
            provider: 'github',
            type: 'github',
            displayName: 'Test Repo',
            canonicalUrl: 'https://github.com/acme/test',
            url: 'https://github.com/acme/test',
            owner: 'acme',
            repository: 'test',
            defaultRef: 'main',
            organizationId: self::ORG_ID,
            visibility: 'public',
            active: true,
            archived: false,
            createdAt: '2024-01-01T00:00:00+00:00',
            providerAuthorizationId: $providerAuthorizationId,
            providerConfig: [
                'webhook_external_id' => 'ext-001',
                ...$extraConfig,
            ],
            authType: 'github_token',
            credentialsConfigured: true,
        );
    }

    private function createBuilder(): SourceDetailsPageDataBuilder
    {
        return new SourceDetailsPageDataBuilder(
            $this->packageApiFacade,
            $this->releaseApiFacade,
            $this->sourceApiFacade,
            $this->sourceSecretResolver,
            $this->webhookApiFacade,
            $this->auditApiFacade,
            new OrganizationPolicy(),
            $this->brandingConfig,
            $this->sourceProviderLabelResolver,
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
