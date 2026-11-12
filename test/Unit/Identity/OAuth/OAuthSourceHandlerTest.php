<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Common\Provider\SourceProviderMetadataCatalog;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Identity\Infrastructure\Dto\OAuthState;
use Packio\Identity\Infrastructure\Handler\OAuthSourceCallbackHandler;
use Packio\Identity\Infrastructure\Handler\OAuthSourceRedirectHandler;
use Packio\Identity\Infrastructure\Handler\SourceOAuthPopupRenderer;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use Packio\Identity\Infrastructure\OAuth\SourceOAuthCallbackWorkflow;
use Packio\Identity\Infrastructure\Support\OAuthCallbackStateValidator;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Source\Api\Dto\SourceOAuthAccountDto;
use Packio\Source\Api\Dto\SourceProviderInstanceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use PackioTest\Support\Fake\InMemorySession;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function preg_match;

#[CoversClass(OAuthSourceRedirectHandler::class)]
#[CoversClass(OAuthSourceCallbackHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class OAuthSourceHandlerTest extends TestCase
{
    private const string PROVIDER_GITHUB             = 'github';
    private const string PROVIDER_CODEBERG           = 'codeberg';
    private const string PROVIDER_GITLAB_SELF_HOSTED = 'gitlab_self_hosted';

    private InertiaNotificationMessenger $inertiaNotificationMessenger;
    private MockObject&OAuthApiFacadeInterface $oauthApi;
    private OAuthSessionContext $oAuthSessionContext;
    private OAuthProviderAvailabilityResolver $oAuthProviderAvailabilityResolver;
    private OAuthCallbackStateValidator $oAuthCallbackStateValidator;
    private MockObject&SourceApiFacadeInterface $sourceApi;

    protected function setUp(): void
    {
        $this->inertiaNotificationMessenger       = new InertiaNotificationMessenger();
        $this->oauthApi                           = $this->createMock(OAuthApiFacadeInterface::class);
        $this->oAuthSessionContext                = new OAuthSessionContext(new SecretEncryptorStub());
        $this->oAuthCallbackStateValidator        = new OAuthCallbackStateValidator();
        $this->sourceApi                          = $this->createMock(SourceApiFacadeInterface::class);

        $config = [
            'github'    => [
                'enabled' => true,
                'source'  => [
                    'enabled'       => true,
                    'client_id'     => 'github-source-client',
                    'client_secret' => 'github-source-secret',
                ],
            ],
            'gitlab'    => [
                'enabled' => false,
            ],
            'bitbucket' => [
                'enabled' => false,
            ],
            'codeberg'  => [
                'enabled' => true,
                'source'  => [
                    'enabled'       => true,
                    'client_id'     => 'codeberg-source-client',
                    'client_secret' => 'codeberg-source-secret',
                ],
            ],
        ];
        $projectSettings = $this->createMock(ProjectSettingsApiFacadeInterface::class);
        $projectSettings
            ->method('booleanValue')
            ->willReturnCallback(static fn (string $key, bool $fallback): bool => 'oauth.gitlab_self_hosted.source.enabled' === $key ? true : $fallback)
        ;
        $this->oAuthProviderAvailabilityResolver = new OAuthProviderAvailabilityResolver($config, new SourceProviderMetadataCatalog(), $projectSettings);
    }

    // ── OAuthSourceRedirectHandler ──────────────────────────────────────────

    #[Test]
    public function sourceRedirectRouteUsesOrganizationSourcesManagePermission(): void
    {
        $reflectionClass = new ReflectionClass(OAuthSourceRedirectHandler::class);
        $can             = $reflectionClass->getAttributes(Can::class)[0]->newInstance();
        $get             = $reflectionClass->getAttributes(Get::class)[0]->newInstance();

        self::assertSame('sources.manage', $can->permission);
        self::assertSame([
            'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
            'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
        ], $can->context);
        self::assertSame('/organization/:organization/auth/oauth/:provider/source-redirect', $get->path);
        self::assertSame([RequireExistingOrganizationMiddleware::class], $get->middleware);
    }

    #[Test]
    public function sourceRedirectReturnsRedirectForValidProvider(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
        ;

        $this->oauthApi->expects(self::once())
            ->method('getSourceAuthorizationUrl')
            ->with(self::PROVIDER_GITHUB, self::isString(), null)
            ->willReturn('https://github.com/login/oauth/authorize?scope=repo')
        ;

        $oAuthSourceRedirectHandler = new OAuthSourceRedirectHandler(
            $this->oauthApi,
            $this->oAuthSessionContext,
            $this->oAuthProviderAvailabilityResolver,
            $this->sourceApi,
        );

        $response = $oAuthSourceRedirectHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('github.com', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function sourceRedirectAddsPkceChallengeForCodeberg(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_CODEBERG)
            ->withAttribute('organization', 'my-org')
        ;

        $this->oauthApi->expects(self::once())
            ->method('getSourceAuthorizationUrl')
            ->with(
                self::PROVIDER_CODEBERG,
                self::isString(),
                self::callback(static fn (string $codeChallenge): bool => 1 === preg_match('/^[A-Za-z0-9_-]{43}$/', $codeChallenge)),
            )
            ->willReturn('https://codeberg.org/login/oauth/authorize?scope=repo')
        ;

        $oAuthSourceRedirectHandler = new OAuthSourceRedirectHandler(
            $this->oauthApi,
            $this->oAuthSessionContext,
            $this->oAuthProviderAvailabilityResolver,
            $this->sourceApi,
        );

        $response = $oAuthSourceRedirectHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', (string) $inMemorySession->get('oauth_pkce_code_verifier', ''));
    }

    #[Test]
    public function sourceRedirectStoresStateAndSourceContext(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withAttribute('organization', 'my-org')
            ->withQueryParams([
                'popup' => '1',
            ])
        ;

        $this->oauthApi->expects(self::once())
            ->method('getSourceAuthorizationUrl')
            ->willReturn('https://example.com/oauth')
        ;

        $oAuthSourceRedirectHandler = new OAuthSourceRedirectHandler(
            $this->oauthApi,
            $this->oAuthSessionContext,
            $this->oAuthProviderAvailabilityResolver,
            $this->sourceApi,
        );

        $oAuthSourceRedirectHandler->handle($serverRequest);

        $storedState = $inMemorySession->get('oauth_state', '');
        self::assertNotEmpty($storedState);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $storedState);
        self::assertSame('my-org', $inMemorySession->get('oauth_connect_organization', ''));
        self::assertTrue($inMemorySession->get('oauth_source_popup', false));
    }

    #[Test]
    public function sourceRedirectReturnsJsonErrorForEmptyProvider(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', '')
        ;

        $oAuthSourceRedirectHandler = new OAuthSourceRedirectHandler(
            $this->oauthApi,
            $this->oAuthSessionContext,
            $this->oAuthProviderAvailabilityResolver,
            $this->sourceApi,
        );

        $response = $oAuthSourceRedirectHandler->handle($serverRequest);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function sourceRedirectReturnsJsonErrorForDisabledProvider(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', 'gitlab')
        ;

        $oAuthSourceRedirectHandler = new OAuthSourceRedirectHandler(
            $this->oauthApi,
            $this->oAuthSessionContext,
            $this->oAuthProviderAvailabilityResolver,
            $this->sourceApi,
        );

        $response = $oAuthSourceRedirectHandler->handle($serverRequest);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function sourceRedirectEncodesConnectSourceState(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withAttribute('organization', 'my-org')
            ->withQueryParams([
                'popup' => '1',
            ])
        ;

        $capturedState = null;
        $this->oauthApi->expects(self::once())
            ->method('getSourceAuthorizationUrl')
            ->with(
                self::PROVIDER_GITHUB,
                self::callback(function(string $state) use (&$capturedState): bool {
                    $capturedState = $state;

                    return true;
                }),
            )
            ->willReturn('https://example.com/oauth')
        ;

        $oAuthSourceRedirectHandler = new OAuthSourceRedirectHandler(
            $this->oauthApi,
            $this->oAuthSessionContext,
            $this->oAuthProviderAvailabilityResolver,
            $this->sourceApi,
        );

        $oAuthSourceRedirectHandler->handle($serverRequest);

        self::assertNotNull($capturedState);
        // Decode and verify it's a connect_source action
        $decoded = OAuthState::decode($capturedState);
        self::assertInstanceOf(OAuthState::class, $decoded);
        self::assertTrue($decoded->isConnectSourceAction());
    }

    #[Test]
    public function sourceRedirectForGitlabSelfHostedUsesProviderInstanceAuthorizationUrl(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITLAB_SELF_HOSTED)
            ->withAttribute('organization', 'my-org')
            ->withAttribute(RequestContextAttributes::CURRENT_ORGANIZATION, new OrganizationDto(
                id: 'org-1',
                slug: 'my-org',
                name: 'My Org',
                anonymousAccess: true,
                active: true,
            ))
            ->withQueryParams([
                'provider_instance_id' => 'instance-1',
            ])
        ;

        $capturedState = null;
        $this->sourceApi->expects(self::once())
            ->method('sourceProviderInstances')
            ->with('org-1', self::PROVIDER_GITLAB_SELF_HOSTED)
            ->willReturn([
                new SourceProviderInstanceDto(
                    id: 'instance-1',
                    organizationId: 'org-1',
                    providerType: self::PROVIDER_GITLAB_SELF_HOSTED,
                    label: 'Company GitLab',
                    baseUrl: 'https://gitlab.company.test',
                    clientId: 'client-id',
                    hasClientSecret: true,
                    authMode: 'oauth2_and_token',
                    status: 'active',
                ),
            ])
        ;
        $this->sourceApi->expects(self::once())
            ->method('sourceProviderInstanceAuthorizationUrl')
            ->with(
                'my-org',
                self::PROVIDER_GITLAB_SELF_HOSTED,
                'instance-1',
                self::callback(function(string $state) use (&$capturedState): bool {
                    $capturedState = $state;

                    return true;
                }),
                null,
            )
            ->willReturn('https://gitlab.company.test/oauth/authorize')
        ;
        $this->oauthApi->expects(self::never())->method('getSourceAuthorizationUrl');

        $oAuthSourceRedirectHandler = new OAuthSourceRedirectHandler(
            $this->oauthApi,
            $this->oAuthSessionContext,
            $this->oAuthProviderAvailabilityResolver,
            $this->sourceApi,
        );

        $response = $oAuthSourceRedirectHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://gitlab.company.test/oauth/authorize', $response->getHeaderLine('Location'));
        self::assertSame('instance-1', $inMemorySession->get('oauth_source_provider_instance_id', ''));

        self::assertNotNull($capturedState);
        $decoded = OAuthState::decode($capturedState);
        self::assertInstanceOf(OAuthState::class, $decoded);
        self::assertSame(self::PROVIDER_GITLAB_SELF_HOSTED, $decoded->provider);
    }

    // ── OAuthSourceCallbackHandler ──────────────────────────────────────────

    #[Test]
    public function sourceCallbackReturnsJsonErrorForEmptyProvider(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', '')
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function sourceCallbackReturnsJsonErrorForDisabledProvider(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', 'gitlab')
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function sourceCallbackRedirectsToLoginWhenUserNotAuthenticated(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-raw-state';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, 'my-org', false);

        $encodedState  = OAuthState::forConnectSource($rawState, 'my-org', 'github')->encode();
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $encodedState,
                'code'  => 'test-code',
            ])
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function sourceCallbackRedirectsOnStateMismatch(): void
    {
        $inMemorySession = new InMemorySession();
        $this->oAuthSessionContext->storeState($inMemorySession, 'stored-state');
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, 'my-org', false);

        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => 'different-state',
                'code'  => 'test-code',
            ])
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organizations', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function sourceCallbackPassesPkceVerifierForCodeberg(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-raw-state';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, 'my-org', false);
        $this->oAuthSessionContext->storePkceCodeVerifier($inMemorySession, 'stored-verifier');

        $encodedState  = OAuthState::forConnectSource($rawState, 'my-org', self::PROVIDER_CODEBERG)->encode();
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_CODEBERG)
            ->withQueryParams([
                'state' => $encodedState,
                'code'  => 'test-code',
            ])
        ;

        $userDto = new UserDto(
            id: 'user-1',
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['user'],
            createdAt: '2025-01-01T00:00:00+00:00',
        );
        $serverRequest = $serverRequest->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto);

        $this->sourceApi->expects(self::once())
            ->method('handleSourceOAuthCallback')
            ->with(self::PROVIDER_CODEBERG, 'test-code', 'user-1', 'my-org', 'stored-verifier')
            ->willReturn(new SourceOAuthAccountDto('soa-1', self::PROVIDER_CODEBERG, 'cb-user', 'cb@example.com'))
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/organization/my-org/sources?oauth_just_connected=1', $response->getHeaderLine('Location'));
        self::assertFalse($inMemorySession->has('oauth_pkce_code_verifier'));
    }

    #[Test]
    public function sourceCallbackReturnsPopupSuccessHtml(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-raw-state';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, 'my-org', true); // popup = true

        $encodedState  = OAuthState::forConnectSource($rawState, 'my-org', 'github')->encode();
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $encodedState,
                'code'  => 'test-code',
            ])
        ;

        $userDto = new UserDto(
            id: 'user-1',
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['user'],
            createdAt: '2025-01-01T00:00:00+00:00',
        );
        $serverRequest = $serverRequest->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto);
        $this->sourceApi->expects(self::once())
            ->method('handleSourceOAuthCallback')
            ->willReturn(new SourceOAuthAccountDto('soa-1', 'github', 'gh-user', 'gh@example.com'))
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(HtmlResponse::class, $response);
        self::assertStringContainsString('window.close()', (string) $response->getBody());
        self::assertStringContainsString('window.location.origin', (string) $response->getBody());
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function sourceCallbackReturnsPopupFailureHtmlOnException(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-raw-state';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, 'my-org', true); // popup = true

        $encodedState  = OAuthState::forConnectSource($rawState, 'my-org', 'github')->encode();
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $encodedState,
                'code'  => 'test-code',
            ])
        ;

        $userDto = new UserDto(
            id: 'user-1',
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['user'],
            createdAt: '2025-01-01T00:00:00+00:00',
        );
        $serverRequest = $serverRequest->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto);
        $this->sourceApi->expects(self::once())
            ->method('handleSourceOAuthCallback')
            ->willThrowException(new RuntimeException('Token exchange failed'))
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(HtmlResponse::class, $response);
        self::assertStringContainsString('window.close()', (string) $response->getBody());
        self::assertStringContainsString('window.location.origin', (string) $response->getBody());
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function sourceCallbackClosesPopupOnProviderDenial(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-raw-state';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, 'my-org', true); // popup = true

        $encodedState  = OAuthState::forConnectSource($rawState, 'my-org', 'github')->encode();
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $encodedState,
                'error' => 'access_denied',
            ])
        ;

        $userDto = new UserDto(
            id: 'user-1',
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['user'],
            createdAt: '2025-01-01T00:00:00+00:00',
        );
        $serverRequest = $serverRequest->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto);
        $this->sourceApi->expects(self::never())->method('handleSourceOAuthCallback');

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(HtmlResponse::class, $response);
        self::assertStringContainsString('window.close()', (string) $response->getBody());
        self::assertStringContainsString('window.location.origin', (string) $response->getBody());
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function sourceCallbackNonPopupRedirectsToOrganizationSources(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-raw-state';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, 'my-org', false);

        $encodedState  = OAuthState::forConnectSource($rawState, 'my-org', 'github')->encode();
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $encodedState,
                'code'  => 'test-code',
            ])
        ;

        $userDto = new UserDto(
            id: 'user-1',
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['user'],
            createdAt: '2025-01-01T00:00:00+00:00',
        );
        $serverRequest = $serverRequest->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto);

        $sourceOAuthAccountDto = new SourceOAuthAccountDto(
            id: 'soa-1',
            provider: 'github',
            providerUsername: 'gh-user',
            providerEmail: 'gh@example.com',
        );
        $this->sourceApi->expects(self::once())
            ->method('handleSourceOAuthCallback')
            ->willReturn($sourceOAuthAccountDto)
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organization/my-org/sources?oauth_just_connected=1', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function sourceCallbackNonPopupRedirectsToOrganizationsWhenNoSlug(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-raw-state';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, '', false);

        $encodedState  = OAuthState::forConnectSource($rawState, null, 'github')->encode();
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $encodedState,
                'code'  => 'test-code',
            ])
        ;

        $userDto = new UserDto(
            id: 'user-1',
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['user'],
            createdAt: '2025-01-01T00:00:00+00:00',
        );
        $serverRequest = $serverRequest->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto);

        $sourceOAuthAccountDto = new SourceOAuthAccountDto(
            id: 'soa-1',
            provider: 'github',
            providerUsername: 'gh-user',
            providerEmail: 'gh@example.com',
        );
        $this->sourceApi->expects(self::once())
            ->method('handleSourceOAuthCallback')
            ->willReturn($sourceOAuthAccountDto)
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organizations', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function sourceCallbackRejectsInvalidDecodedState(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-raw-state';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, 'my-org', false);

        $oAuthState    = OAuthState::forLogin($rawState);
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $oAuthState->encode(),
                'code'  => 'test-code',
            ])
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organizations', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function sourceCallbackRejectsStateWithMismatchedProvider(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-raw-state';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, 'my-org', false);

        $oAuthState    = OAuthState::forConnectSource($rawState, 'my-org', 'gitlab');
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $oAuthState->encode(),
                'code'  => 'test-code',
            ])
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $response = $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organizations', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function sourceCallbackClearsSourceContextAfterSuccess(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-raw-state';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, 'my-org', false);

        $encodedState  = OAuthState::forConnectSource($rawState, 'my-org', 'github')->encode();
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $encodedState,
                'code'  => 'test-code',
            ])
        ;

        $userDto = new UserDto(
            id: 'user-1',
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['user'],
            createdAt: '2025-01-01T00:00:00+00:00',
        );
        $serverRequest = $serverRequest->withAttribute(RequestContextAttributes::CURRENT_USER, $userDto);
        $this->sourceApi->expects(self::once())
            ->method('handleSourceOAuthCallback')
            ->willReturn(new SourceOAuthAccountDto('soa-1', 'github', 'u', 'e'))
        ;

        $oAuthSourceCallbackHandler = $this->callbackHandler();

        $oAuthSourceCallbackHandler->handle($serverRequest);

        self::assertFalse($inMemorySession->has('oauth_connect_organization'));
        self::assertFalse($inMemorySession->has('oauth_source_popup'));
    }

    #[Test]
    public function sourceCallbackForGitlabSelfHostedUsesProviderInstanceCallback(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-raw-state';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeSourceContext($inMemorySession, 'my-org', false, 'instance-1');

        $encodedState  = OAuthState::forConnectSource($rawState, 'my-org', self::PROVIDER_GITLAB_SELF_HOSTED)->encode();
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITLAB_SELF_HOSTED)
            ->withQueryParams([
                'state' => $encodedState,
                'code'  => 'test-code',
            ])
            ->withAttribute(RequestContextAttributes::CURRENT_USER, new UserDto(
                id: 'user-1',
                email: 'user@example.com',
                displayName: 'Test User',
                roles: ['user'],
                createdAt: '2025-01-01T00:00:00+00:00',
            ))
        ;

        $this->sourceApi->expects(self::once())
            ->method('handleSourceProviderInstanceOAuthCallback')
            ->with(
                self::PROVIDER_GITLAB_SELF_HOSTED,
                'test-code',
                'user-1',
                'my-org',
                'instance-1',
                null,
            )
            ->willReturn(new SourceOAuthAccountDto('soa-1', self::PROVIDER_GITLAB_SELF_HOSTED, 'gitlab-user', 'gitlab@example.com'))
        ;
        $this->sourceApi->expects(self::never())->method('handleSourceOAuthCallback');

        $response = $this->callbackHandler()->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/organization/my-org/sources?oauth_just_connected=1', $response->getHeaderLine('Location'));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function callbackHandler(): OAuthSourceCallbackHandler
    {
        return new OAuthSourceCallbackHandler(
            $this->oAuthSessionContext,
            $this->oAuthCallbackStateValidator,
            $this->oAuthProviderAvailabilityResolver,
            new SourceOAuthCallbackWorkflow($this->sourceApi),
            $this->inertiaNotificationMessenger,
            new SourceOAuthPopupRenderer(),
        );
    }

    private function buildRequest(InMemorySession $inMemorySession): ServerRequest
    {
        return new ServerRequest()
            ->withHeader('X-Inertia', 'true')
            ->withAttribute('session', $inMemorySession)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia())
        ;
    }
}
