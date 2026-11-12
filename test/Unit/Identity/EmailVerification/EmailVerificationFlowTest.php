<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\EmailVerification;

use Common\Security\SecretEncryptorInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Security\Turnstile\TurnstileVerifierInterface;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Api\EmailVerificationReasonType;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\OAuth\OAuthContinuationInterface;
use Packio\Identity\Application\Query\IdentityQueryService;
use Packio\Identity\Application\Result\OAuthCallbackResult;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Application\Security\RegistryAccessTokenSecrets;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use Packio\Identity\Infrastructure\Dto\PendingOAuthEmailContext;
use Packio\Identity\Infrastructure\Handler\EmailVerificationResendHandler;
use Packio\Identity\Infrastructure\Handler\EmailVerificationVerifyHandler;
use Packio\Identity\Infrastructure\Handler\OAuthOutcomePresenter;
use Packio\Identity\Infrastructure\Request\EmailVerificationCodeRequest;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use PackioTest\Support\Fake\InMemorySession;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

#[CoversClass(EmailVerificationVerifyHandler::class)]
#[CoversClass(EmailVerificationResendHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class EmailVerificationFlowTest extends TestCase
{
    #[Test]
    public function verifyHandlerRedirectsToLoginWhenPendingUserMissing(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = new ServerRequest()
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia())
            ->withAttribute('session', $inMemorySession)
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(EmailVerificationCodeRequest::class, new EmailVerificationCodeRequest('123456'))
        ;
        $inertiaNotificationMessenger = new InertiaNotificationMessenger();

        $identityApi = $this->createMock(OAuthContinuationInterface::class);
        $identityApi->expects(self::never())->method('verify');

        $authManager = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::never())->method('login');

        $emailVerificationVerifyHandler = new EmailVerificationVerifyHandler(
            $identityApi,
            new OAuthOutcomePresenter($authManager, new OAuthSessionContext(new SecretEncryptorStub()), new PendingEmailVerificationSession(), $inertiaNotificationMessenger, $this->identityQueryService()),
            $inertiaNotificationMessenger,
            new SafeRedirectTarget(),
            $this->disabledTurnstileGuard(),
            new OAuthSessionContext(new SecretEncryptorStub()),
            new PendingEmailVerificationSession(),
        );

        $response = $emailVerificationVerifyHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function invalidVerificationCodeRedirectsBackWithSessionErrors(): void
    {
        $inMemorySession         = new InMemorySession();
        $testInertia             = new TestInertia();
        $validationRedirectState = new ValidationRedirectState();
        $serverRequest           = new ServerRequest()
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
            ->withAttribute('session', $inMemorySession)
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(EmailVerificationCodeRequest::class, new EmailVerificationCodeRequest('123456'))
        ;
        $inertiaNotificationMessenger = new InertiaNotificationMessenger();

        $identityApi = $this->createMock(OAuthContinuationInterface::class);
        $identityApi->expects(self::once())
            ->method('verify')
            ->with('user-uuid', '123456', self::anything(), false)
            ->willReturn(new OAuthCallbackResult(OAuthCallbackStatus::InvalidCode))
        ;

        $authManager = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::never())->method('login');

        $inMemorySession->set('pending_verification_user_uuid', 'user-uuid');

        $emailVerificationVerifyHandler = new EmailVerificationVerifyHandler(
            $identityApi,
            new OAuthOutcomePresenter($authManager, new OAuthSessionContext(new SecretEncryptorStub()), new PendingEmailVerificationSession(), $inertiaNotificationMessenger, $this->identityQueryService()),
            $inertiaNotificationMessenger,
            new SafeRedirectTarget(),
            $this->disabledTurnstileGuard(),
            new OAuthSessionContext(new SecretEncryptorStub()),
            new PendingEmailVerificationSession(),
            $validationRedirectState,
        );

        $response = $emailVerificationVerifyHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/verification', $response->getHeaderLine('Location'));
        self::assertSame([
            'code' => ['Invalid code.'],
        ], $validationRedirectState->consumeErrors($serverRequest));
        self::assertSame([], $validationRedirectState->consumeOld($serverRequest));
    }

    #[Test]
    public function successfulVerificationAttachesTokenAndRedirectsToStoredDestination(): void
    {
        $inMemorySession     = new InMemorySession();
        $testInertia         = new TestInertia();
        $serverRequest       = new ServerRequest()
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
            ->withAttribute('session', $inMemorySession)
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(EmailVerificationCodeRequest::class, new EmailVerificationCodeRequest('123456'))
        ;
        $inertiaNotificationMessenger = new InertiaNotificationMessenger();

        $identityApi = $this->createMock(OAuthContinuationInterface::class);
        $identityApi->expects(self::once())
            ->method('verify')
            ->with('user-uuid', '123456', self::anything(), false)
            ->willReturn(new OAuthCallbackResult(OAuthCallbackStatus::Authenticated, userId: 'user-uuid'))
        ;

        $authManager = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::once())
            ->method('login')
            ->with($serverRequest, self::isInstanceOf(RedirectResponse::class), [
                'user_id'                 => 'user-uuid',
                'credentials_fingerprint' => null,
            ])
            ->willReturnCallback(static fn (ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface => $response)
        ;

        $inMemorySession->set('pending_verification_user_uuid', 'user-uuid');
        $inMemorySession->set('pending_verification_redirect_to', '/target');

        $emailVerificationVerifyHandler = new EmailVerificationVerifyHandler(
            $identityApi,
            new OAuthOutcomePresenter($authManager, new OAuthSessionContext(new SecretEncryptorStub()), new PendingEmailVerificationSession(), $inertiaNotificationMessenger, $this->identityQueryService()),
            $inertiaNotificationMessenger,
            new SafeRedirectTarget(),
            $this->disabledTurnstileGuard(),
            new OAuthSessionContext(new SecretEncryptorStub()),
            new PendingEmailVerificationSession(),
        );

        $response = $emailVerificationVerifyHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/target', $response->getHeaderLine('Location'));
        self::assertFalse($inMemorySession->has('pending_verification_user_uuid'));
        self::assertFalse($inMemorySession->has('pending_verification_redirect_to'));
        self::assertSame([[
            'type'     => 'success',
            'message'  => 'Email verified.',
            'duration' => null,
            'position' => null,
        ]], $testInertia->flash['toasts']);
    }

    #[Test]
    public function successfulVerificationLinksPendingOAuthAccount(): void
    {
        $inMemorySession = new InMemorySession();
        $serverRequest   = new ServerRequest()
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia())
            ->withAttribute('session', $inMemorySession)
            ->withAttribute(SessionInterface::class, $inMemorySession)
            ->withAttribute(EmailVerificationCodeRequest::class, new EmailVerificationCodeRequest('123456'))
        ;
        $inertiaNotificationMessenger = new InertiaNotificationMessenger();

        $identityApi = $this->createMock(OAuthContinuationInterface::class);
        $identityApi->expects(self::once())
            ->method('verify')
            ->with('user-uuid', '123456', self::anything(), false)
            ->willReturn(new OAuthCallbackResult(OAuthCallbackStatus::Authenticated, userId: 'user-uuid'))
        ;

        $authManager = $this->createMock(AuthManagerInterface::class);
        $authManager->expects(self::once())
            ->method('login')
            ->willReturnCallback(static fn (ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface => $response)
        ;

        $oAuthSessionContext = new OAuthSessionContext(new SecretEncryptorStub());
        $oAuthSessionContext->storePendingEmailContext(
            $inMemorySession,
            PendingOAuthEmailContext::fromOAuth(
                OAuthProvider::Codeberg,
                new OAuthTokenResponse('access-token', null, null),
                new OAuthUserData('codeberg-user-1', null, 'cb-user', 'Codeberg User', null),
                '',
            ),
        );
        $inMemorySession->set('pending_verification_user_uuid', 'user-uuid');

        $emailVerificationVerifyHandler = new EmailVerificationVerifyHandler(
            $identityApi,
            new OAuthOutcomePresenter($authManager, new OAuthSessionContext(new SecretEncryptorStub()), new PendingEmailVerificationSession(), $inertiaNotificationMessenger, $this->identityQueryService()),
            $inertiaNotificationMessenger,
            new SafeRedirectTarget(),
            $this->disabledTurnstileGuard(),
            $oAuthSessionContext,
            new PendingEmailVerificationSession(),
        );

        $response = $emailVerificationVerifyHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertFalse($inMemorySession->has('oauth_pending_email_context'));
    }

    #[Test]
    public function resendVerificationRedirectsBackWithSuccessFlash(): void
    {
        $inMemorySession     = new InMemorySession();
        $testInertia         = new TestInertia();
        $serverRequest       = new ServerRequest()
            ->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, $testInertia)
            ->withAttribute('session', $inMemorySession)
            ->withAttribute(SessionInterface::class, $inMemorySession)
        ;
        $inertiaNotificationMessenger = new InertiaNotificationMessenger();

        $identityApi = $this->createMock(IdentityApiFacadeInterface::class);
        $identityApi->expects(self::once())
            ->method('emailVerificationRemainingResendDelay')
            ->with('user-uuid')
            ->willReturn(0)
        ;
        $identityApi->expects(self::once())
            ->method('requestEmailVerification')
            ->with('user-uuid', true, EmailVerificationReasonType::ManualResend)
        ;

        $inMemorySession->set('pending_verification_user_uuid', 'user-uuid');

        $emailVerificationResendHandler = new EmailVerificationResendHandler(
            $identityApi,
            $inertiaNotificationMessenger,
            $this->disabledTurnstileGuard(),
        );

        $response = $emailVerificationResendHandler->handle($serverRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/verification', $response->getHeaderLine('Location'));
        self::assertSame([
            'success' => 'Verification code sent.',
        ], $testInertia->flash);
    }

    private function disabledTurnstileGuard(): TurnstileRequestGuard
    {
        return new TurnstileRequestGuard(
            $this->createMock(TurnstileVerifierInterface::class),
            $this->createMock(ClientIpResolverInterface::class),
            [
                'enabled'    => false,
                'site_key'   => '',
                'secret_key' => '',
            ],
        );
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
