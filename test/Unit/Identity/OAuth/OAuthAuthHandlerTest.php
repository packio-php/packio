<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Common\Provider\SourceProviderMetadataCatalog;
use Common\Security\SecretEncryptorInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Identity\Application\Command\OAuth\BrowserOAuthWorkflowInterface;
use Packio\Identity\Application\Command\OAuth\ResolveOAuthCallbackCommand;
use Packio\Identity\Application\Query\IdentityQueryService;
use Packio\Identity\Application\Result\OAuthCallbackResult;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Application\Security\RegistryAccessTokenSecrets;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider as DomainOAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use Packio\Identity\Infrastructure\Dto\OAuthState;
use Packio\Identity\Infrastructure\Handler\OAuthCallbackHandler;
use Packio\Identity\Infrastructure\Handler\OAuthOutcomePresenter;
use Packio\Identity\Infrastructure\Handler\OAuthRedirectHandler;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;
use Packio\Identity\Infrastructure\Support\OAuthCallbackStateValidator;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use PackioTest\Support\Fake\FallbackAvailabilityCollaborators;
use PackioTest\Support\Fake\InMemorySession;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

use function preg_match;

#[CoversClass(OAuthRedirectHandler::class)]
#[CoversClass(OAuthCallbackHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class OAuthAuthHandlerTest extends TestCase
{
    use FallbackAvailabilityCollaborators;

    private const string PROVIDER_GITHUB   = 'github';
    private const string PROVIDER_CODEBERG = 'codeberg';

    private InertiaNotificationMessenger $inertiaNotificationMessenger;
    private MockObject&OAuthApiFacadeInterface $oauthApi;
    private BrowserOAuthWorkflowInterface&MockObject $workflow;
    private OAuthSessionContext $oAuthSessionContext;
    private OAuthProviderAvailabilityResolver $oAuthProviderAvailabilityResolver;
    private AuthManagerInterface&MockObject $authManager;
    private OAuthCallbackStateValidator $oAuthCallbackStateValidator;
    private SafeRedirectTarget $safeRedirectTarget;
    private TestInertia $testInertia;
    private OAuthCallbackHandler $oAuthCallbackHandler;
    private OAuthRedirectHandler $oAuthRedirectHandler;

    protected function setUp(): void
    {
        $this->inertiaNotificationMessenger       = new InertiaNotificationMessenger();
        $this->oauthApi                           = $this->createMock(OAuthApiFacadeInterface::class);
        $this->workflow                           = $this->createMock(BrowserOAuthWorkflowInterface::class);
        $this->oAuthSessionContext                = new OAuthSessionContext(new SecretEncryptorStub());
        $this->authManager                        = $this->createMock(AuthManagerInterface::class);
        $this->oAuthCallbackStateValidator        = new OAuthCallbackStateValidator();
        $this->safeRedirectTarget                 = new SafeRedirectTarget();
        $this->testInertia                        = new TestInertia();

        $config = [
            'github'    => [
                'enabled'       => true,
                'client_id'     => 'github-client',
                'client_secret' => 'github-secret',
            ],
            'gitlab'    => [
                'enabled' => false,
            ],
            'bitbucket' => [
                'enabled' => false,
            ],
            'codeberg'  => [
                'enabled'       => true,
                'client_id'     => 'codeberg-client',
                'client_secret' => 'codeberg-secret',
            ],
        ];

        $this->oAuthProviderAvailabilityResolver = new OAuthProviderAvailabilityResolver($config, new SourceProviderMetadataCatalog(), $this->fallbackProjectSettings());

        $this->oAuthCallbackHandler = new OAuthCallbackHandler(
            $this->workflow,
            new OAuthOutcomePresenter($this->authManager, $this->oAuthSessionContext, new PendingEmailVerificationSession(), $this->inertiaNotificationMessenger, $this->identityQueryService()),
            $this->oAuthSessionContext,
            $this->oAuthCallbackStateValidator,
            $this->oAuthProviderAvailabilityResolver,
            $this->inertiaNotificationMessenger,
            $this->safeRedirectTarget,
        );

        $this->oAuthRedirectHandler = new OAuthRedirectHandler(
            $this->oauthApi,
            $this->oAuthSessionContext,
            $this->oAuthProviderAvailabilityResolver,
            $this->inertiaNotificationMessenger,
            $this->safeRedirectTarget,
        );
    }

    #[Test]
    public function redirectHandlerRedirectsToProviderUrlForValidProvider(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
        ;

        $this->oauthApi->expects(self::once())
            ->method('getAuthorizationUrl')
            ->with(self::PROVIDER_GITHUB, self::isString(), null)
            ->willReturn('https://github.com/login/oauth/authorize?client_id=test')
        ;

        $response = $this->oAuthRedirectHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('github.com', $response->getHeaderLine('Location'));
        self::assertNotEmpty($inMemorySession->get('oauth_state', ''));
    }

    #[Test]
    public function redirectHandlerAddsPkceChallengeForCodeberg(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_CODEBERG)
        ;

        $this->oauthApi->expects(self::once())
            ->method('getAuthorizationUrl')
            ->with(
                self::PROVIDER_CODEBERG,
                self::isString(),
                self::callback(static fn (string $codeChallenge): bool => 1 === preg_match('/^[A-Za-z0-9_-]{43}$/', $codeChallenge)),
            )
            ->willReturn('https://codeberg.org/login/oauth/authorize?client_id=test')
        ;

        $response = $this->oAuthRedirectHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', (string) $inMemorySession->get('oauth_pkce_code_verifier', ''));
    }

    #[Test]
    public function redirectHandlerStoresStateInSession(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
        ;

        $this->oauthApi->expects(self::once())
            ->method('getAuthorizationUrl')
            ->willReturn('https://example.com/oauth')
        ;

        $this->oAuthRedirectHandler->handle($serverRequest);

        $storedState = $inMemorySession->get('oauth_state', '');
        self::assertNotEmpty($storedState);
        // Should be a hex string (32 chars for 16 random bytes)
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $storedState);
    }

    #[Test]
    public function redirectHandlerStoresSafeRedirectTargetInSession(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'redirect_to' => '/organization/invite/orgi_test',
            ])
        ;

        $this->oauthApi->expects(self::once())
            ->method('getAuthorizationUrl')
            ->willReturn('https://example.com/oauth')
        ;

        $this->oAuthRedirectHandler->handle($serverRequest);

        self::assertSame('/organization/invite/orgi_test', $this->oAuthSessionContext->consumeRedirectTarget($inMemorySession));
    }

    #[Test]
    public function redirectHandlerFlashesErrorForEmptyProvider(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', '')
        ;

        $response = $this->oAuthRedirectHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'OAuth provider not supported.',
        ], $this->testInertia->flash);
    }

    #[Test]
    public function redirectHandlerFlashesErrorForDisabledProvider(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', 'gitlab') // GitLab is disabled in config
        ;

        $response = $this->oAuthRedirectHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'OAuth provider not supported.',
        ], $this->testInertia->flash);
    }

    #[Test]
    public function redirectHandlerFlashesErrorForBitbucket(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', 'bitbucket')
        ;

        $response = $this->oAuthRedirectHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'OAuth provider not supported.',
        ], $this->testInertia->flash);
    }

    #[Test]
    public function callbackRedirectsToLoginForEmptyProvider(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', '')
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
        self::assertSame([
            'error' => 'OAuth provider not supported.',
        ], $this->testInertia->flash);
    }

    #[Test]
    public function callbackRedirectsToLoginForDisabledProvider(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', 'gitlab')
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function callbackRedirectsToSourceCallbackWhenStateHasConnectSourceAction(): void
    {
        $inMemorySession = new InMemorySession();
        $oAuthState      = OAuthState::forConnectSource('rawstate', 'my-org', 'github');
        $serverRequest   = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $oAuthState->encode(),
                'code'  => 'test-code',
            ])
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/auth/oauth/github/source-callback', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function callbackRedirectsToLoginWhenStateIsInvalid(): void
    {
        $inMemorySession = new InMemorySession();
        $this->oAuthSessionContext->storeState($inMemorySession, 'stored-state-value');

        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => 'different-state',
                'code'  => 'test-code',
            ])
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function callbackRedirectsToLoginWhenStateIsDecodedButMismatches(): void
    {
        $inMemorySession = new InMemorySession();
        $this->oAuthSessionContext->storeState($inMemorySession, 'stored-raw-state');

        $oAuthState    = OAuthState::forLogin('different-raw');
        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $oAuthState->encode(),
                'code'  => 'test-code',
            ])
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function callbackRedirectsToLoginOnProviderError(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-state-value';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);

        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state'             => $rawState,
                'error'             => 'access_denied',
                'error_description' => 'User denied access.',
            ])
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
        self::assertSame('OAuth authorization failed. Please try again.', $this->testInertia->flash['toasts'][0]['message'] ?? null);
        self::assertStringNotContainsString('User denied access.', (string) ($this->testInertia->flash['toasts'][0]['message'] ?? ''));
    }

    #[Test]
    public function callbackHandlesRuntimeExceptionFromOAuthApi(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-state-value';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);

        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $rawState,
                'code'  => 'test-code',
            ])
        ;

        $this->workflow->expects(self::once())
            ->method('handle')
            ->with(self::PROVIDER_GITHUB, 'test-code', '', null)
            ->willThrowException(new RuntimeException('Token exchange failed'))
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
        self::assertSame('OAuth authentication failed. Please try again.', $this->testInertia->flash['toasts'][0]['message'] ?? null);
        self::assertStringNotContainsString('Token exchange failed', (string) ($this->testInertia->flash['toasts'][0]['message'] ?? ''));
    }

    #[Test]
    public function callbackLogsInUserAndAttachesToken(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-state-value';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);

        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $rawState,
                'code'  => 'test-code',
            ])
        ;

        $oAuthCallbackResult = new OAuthCallbackResult(
            status: OAuthCallbackStatus::Authenticated,
            userCreated: false,
            userId: 'user-123',
            email: 'user@example.com',
            displayName: 'Test User',
        );

        $this->workflow->expects(self::once())
            ->method('handle')
            ->with(self::PROVIDER_GITHUB, 'test-code', '', null)
            ->willReturn($oAuthCallbackResult)
        ;

        $this->authManager->expects(self::once())
            ->method('login')
            ->with($serverRequest, self::isInstanceOf(RedirectResponse::class), [
                'user_id'                 => 'user-123',
                'credentials_fingerprint' => null,
            ])
            ->willReturnCallback(static fn (ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface => $response)
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/organizations', $response->getHeaderLine('Location'));
        // State should be cleared after successful callback
        self::assertEmpty($inMemorySession->get('oauth_state', ''));
    }

    #[Test]
    public function callbackLogsInABannedUserAndRedirectsToTheBannedPage(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-state-value';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);

        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $rawState,
                'code'  => 'test-code',
            ])
        ;

        $this->workflow->expects(self::once())
            ->method('handle')
            ->willReturn(new OAuthCallbackResult(
                status: OAuthCallbackStatus::Banned,
                userCreated: false,
                userId: 'user-banned',
                email: 'banned@example.test',
                displayName: 'Banned User',
            ))
        ;
        $this->authManager->expects(self::once())
            ->method('login')
            ->with($serverRequest, self::isInstanceOf(RedirectResponse::class), [
                'user_id'                 => 'user-banned',
                'credentials_fingerprint' => null,
            ])
            ->willReturnCallback(static fn (ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface => $response)
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertSame('/account/banned', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function callbackRedirectsToStoredRedirectTargetAfterLogin(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-state-value';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storeRedirectTarget($inMemorySession, '/organization/invite/orgi_test');

        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $rawState,
                'code'  => 'test-code',
            ])
        ;

        $this->workflow->expects(self::once())
            ->method('handle')
            ->with(self::PROVIDER_GITHUB, 'test-code', '/organization/invite/orgi_test', null)
            ->willReturn(new OAuthCallbackResult(
                status: OAuthCallbackStatus::Authenticated,
                userCreated: false,
                userId: 'user-123',
                email: 'user@example.com',
                displayName: 'Test User',
            ))
        ;

        $this->authManager->expects(self::once())
            ->method('login')
            ->willReturnCallback(static fn (ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface => $response)
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/organization/invite/orgi_test', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function callbackPassesPkceVerifierForCodeberg(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-state-value';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);
        $this->oAuthSessionContext->storePkceCodeVerifier($inMemorySession, 'stored-verifier');

        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_CODEBERG)
            ->withQueryParams([
                'state' => $rawState,
                'code'  => 'test-code',
            ])
        ;

        $this->workflow->expects(self::once())
            ->method('handle')
            ->with(self::PROVIDER_CODEBERG, 'test-code', '', 'stored-verifier')
            ->willThrowException(new RuntimeException('Token exchange failed'))
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
        self::assertFalse($inMemorySession->has('oauth_pkce_code_verifier'));
    }

    #[Test]
    public function callbackRedirectsToEmailFormWhenOAuthProviderDoesNotReturnEmail(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-state-value';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);

        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_CODEBERG)
            ->withQueryParams([
                'state' => $rawState,
                'code'  => 'test-code',
            ])
        ;

        $this->workflow->expects(self::once())
            ->method('handle')
            ->willReturn(new OAuthCallbackResult(OAuthCallbackStatus::EmailRequired, pendingOAuth: new ResolveOAuthCallbackCommand(
                DomainOAuthProvider::Codeberg,
                new OAuthTokenResponse('access-token', null, null),
                new OAuthUserData('codeberg-user-1', null, 'cb-user', 'Codeberg User', null),
            )))
        ;

        $response = $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/auth/oauth/email', $response->getHeaderLine('Location'));
        self::assertNotNull($this->oAuthSessionContext->pendingEmailContext($inMemorySession));
    }

    #[Test]
    public function callbackClearsStateAfterSuccess(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-state-value';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);

        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $rawState,
                'code'  => 'test-code',
            ])
        ;

        $oAuthCallbackResult = new OAuthCallbackResult(
            status: OAuthCallbackStatus::Authenticated,
            userCreated: false,
            userId: 'user-123',
            email: 'user@example.com',
            displayName: 'User',
        );

        $this->workflow->expects(self::once())->method('handle')->willReturn($oAuthCallbackResult);
        $this->authManager->expects(self::once())
            ->method('login')
            ->willReturnCallback(static fn (ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface => $response)
        ;

        $this->oAuthCallbackHandler->handle($serverRequest);

        self::assertEmpty($inMemorySession->get('oauth_state', ''));
    }

    #[Test]
    public function callbackRequiresVerificationWithoutLoggingIn(): void
    {
        $inMemorySession = new InMemorySession();
        $rawState        = 'stored-state-value';
        $this->oAuthSessionContext->storeState($inMemorySession, $rawState);

        $serverRequest = $this->buildRequest($inMemorySession)
            ->withAttribute('provider', self::PROVIDER_GITHUB)
            ->withQueryParams([
                'state' => $rawState,
                'code'  => 'test-code',
            ])
        ;

        $oAuthCallbackResult = new OAuthCallbackResult(
            status: OAuthCallbackStatus::VerificationRequired,
            userCreated: false,
            userId: 'user-123',
            email: 'user@example.com',
            displayName: 'User',
        );

        $this->workflow->expects(self::once())->method('handle')->willReturn($oAuthCallbackResult);
        $this->authManager->expects(self::never())->method('login');

        $response = $this->oAuthCallbackHandler->handle($serverRequest);
        self::assertSame('/auth/verification', $response->getHeaderLine('Location'));
        self::assertSame('user-123', new PendingEmailVerificationSession()->userUuid($inMemorySession));

        self::assertEmpty($inMemorySession->get('oauth_state', ''));
    }

    private function buildRequest(SessionInterface $session): ServerRequest
    {
        return new ServerRequest()
            ->withHeader('X-Inertia', 'true')
            ->withAttribute('session', $session)
            ->withAttribute(SessionInterface::class, $session)
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $this->testInertia)
        ;
    }

    private function identityQueryService(): IdentityQueryService
    {
        return new IdentityQueryService(
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(RegistryAccessTokenRepositoryInterface::class),
            $this->createStub(UserOAuthAccountRepositoryInterface::class),
            new RegistryAccessTokenSecrets($this->createStub(SecretEncryptorInterface::class)),
        );
    }
}
