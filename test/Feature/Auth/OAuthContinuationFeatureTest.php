<?php

declare(strict_types=1);

namespace PackioTest\Feature\Auth;

use Cake\Chronos\Chronos;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Command\EmailVerificationService;
use Packio\Identity\Application\Command\OAuth\OAuthAccountLinkerInterface;
use Packio\Identity\Application\Command\OAuth\OAuthCallbackUseCase;
use Packio\Identity\Application\Command\OAuth\OAuthContinuationWorkflow;
use Packio\Identity\Application\Command\VerificationDispatch;
use Packio\Identity\Domain\Entity\EmailVerificationCode;
use Packio\Identity\Domain\Repository\EmailVerificationCodeRepositoryInterface;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use Packio\Identity\Infrastructure\Dto\PendingOAuthEmailContext;
use Packio\Identity\Infrastructure\Handler\EmailVerificationVerifyHandler;
use Packio\Identity\Infrastructure\Handler\LoginHandler;
use Packio\Identity\Infrastructure\Handler\OAuthEmailSubmitHandler;
use Packio\Identity\Infrastructure\Handler\OAuthOutcomePresenter;
use Packio\Identity\Infrastructure\Handler\RegisterHandler;
use Packio\Identity\Infrastructure\OAuth\OAuthCallbackTransaction;
use Packio\Identity\Infrastructure\OAuth\OAuthRegistrationPolicy;
use Packio\Identity\Infrastructure\OAuth\OAuthUserResolver;
use Packio\Identity\Infrastructure\Request\EmailVerificationCodeRequest;
use Packio\Identity\Infrastructure\Request\LoginRequest;
use Packio\Identity\Infrastructure\Request\OAuthEmailRequest;
use Packio\Identity\Infrastructure\Request\RegisterRequest;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Fake\InMemorySession;
use PackioTest\Unit\Application\Middleware\TestInertia;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;

use function bin2hex;
use function password_hash;
use function random_bytes;
use function time;

final class OAuthContinuationFeatureTest extends FeatureTestCase
{
    #[Test]
    public function retryAndReloadDoNotCreateAnotherUserAndVerificationLinksOnlyOnce(): void
    {
        [$session, $request, $userId] = $this->submitEmail();
        $handler                      = $this->getService(OAuthEmailSubmitHandler::class);
        self::assertSame('/auth/verification', $handler->handle($request)->getHeaderLine('Location'));
        self::assertSame($userId, new PendingEmailVerificationSession()->userUuid($session));
        $accounts = $this->getService(UserOAuthAccountRepositoryInterface::class);
        self::assertSame([], $accounts->findAllByUserId($userId));

        $verify  = $this->getService(EmailVerificationVerifyHandler::class);
        $request = $request->withAttribute(EmailVerificationCodeRequest::class, new EmailVerificationCodeRequest('123456'));
        self::assertSame('/organizations', $verify->handle($request)->getHeaderLine('Location'));
        self::assertCount(1, $accounts->findAllByUserId($userId));
        self::assertNull($this->getService(OAuthSessionContext::class)->pendingEmailContext($session));
        self::assertNull(new PendingEmailVerificationSession()->userUuid($session));
        self::assertSame('/auth/login', $verify->handle($request)->getHeaderLine('Location'));
        self::assertCount(1, $accounts->findAllByUserId($userId));
    }

    #[Test]
    public function banBetweenEmailSubmissionAndVerificationPreventsAccountLinking(): void
    {
        [$session, $request, $userId] = $this->submitEmail();
        $identity                     = $this->getService(IdentityApiFacadeInterface::class);
        $admin                        = $identity->createUser('admin-' . bin2hex(random_bytes(6)) . '@example.test', 'Admin', 'Password1!', ['admin']);
        $identity->banUser($userId, $admin->id, 'Violation of project rules.');
        $response = $this->getService(EmailVerificationVerifyHandler::class)->handle($request->withAttribute(EmailVerificationCodeRequest::class, new EmailVerificationCodeRequest('123456')));
        self::assertSame('/account/banned', $response->getHeaderLine('Location'));
        self::assertSame([], $this->getService(UserOAuthAccountRepositoryInterface::class)->findAllByUserId($userId));
        self::assertNull(new PendingEmailVerificationSession()->userUuid($session));
        self::assertNull($this->getService(OAuthSessionContext::class)->pendingEmailContext($session));
    }

    #[Test]
    public function missingPendingContextDoesNotVerifyOrLinkTheUser(): void
    {
        [$session, $request, $userId] = $this->submitEmail();
        $session->unset('oauth_pending_email_context');
        $response = $this->getService(EmailVerificationVerifyHandler::class)->handle($request->withAttribute(EmailVerificationCodeRequest::class, new EmailVerificationCodeRequest('123456')));
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
        self::assertFalse($this->getService(IdentityApiFacadeInterface::class)->findUserById($userId)->isEmailVerified);
        self::assertSame([], $this->getService(UserOAuthAccountRepositoryInterface::class)->findAllByUserId($userId));
    }

    #[Test]
    public function expiredPendingContextDoesNotVerifyOrLinkTheUser(): void
    {
        [$session, $request, $userId] = $this->submitEmail();
        $payload                      = $session->get('oauth_pending_email_context');
        $payload['created_at']        = time() - 901;
        $session->set('oauth_pending_email_context', $payload);
        $response = $this->getService(EmailVerificationVerifyHandler::class)->handle($request->withAttribute(EmailVerificationCodeRequest::class, new EmailVerificationCodeRequest('123456')));
        self::assertSame('/auth/login', $response->getHeaderLine('Location'));
        self::assertFalse($this->getService(IdentityApiFacadeInterface::class)->findUserById($userId)->isEmailVerified);
    }

    #[Test]
    public function failedAccountLinkRollsBackVerificationAndAllowsRetryWithTheSameCode(): void
    {
        [$session, $request, $userId] = $this->submitEmail();
        $users                        = $this->getService(UserRepositoryInterface::class);
        $linker                       = $this->createMock(OAuthAccountLinkerInterface::class);
        $linker->expects(self::once())->method('persistOAuthAccount')->willThrowException(new RuntimeException('Storage unavailable'));
        $transaction                        = $this->getService(OAuthCallbackTransaction::class);
        $oAuthCallbackUseCase               = new OAuthCallbackUseCase($users, $this->getService(UserOAuthAccountRepositoryInterface::class), $this->getService(OAuthRegistrationPolicy::class), $this->getService(OAuthUserResolver::class), $linker, $transaction);
        $oAuthContinuationWorkflow          = new OAuthContinuationWorkflow($oAuthCallbackUseCase, $users, $this->getService(EmailVerificationService::class), $this->getService(VerificationDispatch::class), $transaction);
        $emailVerificationVerifyHandler     = new EmailVerificationVerifyHandler($oAuthContinuationWorkflow, $this->getService(OAuthOutcomePresenter::class), new InertiaNotificationMessenger(), new SafeRedirectTarget(), $this->getService(TurnstileRequestGuard::class), $this->getService(OAuthSessionContext::class));
        $request                            = $request->withAttribute(EmailVerificationCodeRequest::class, new EmailVerificationCodeRequest('123456'));
        self::assertSame('/auth/verification', $emailVerificationVerifyHandler->handle($request)->getHeaderLine('Location'));
        self::assertFalse($users->findById($userId)->isEmailVerified());
        self::assertNotNull($this->getService(EmailVerificationCodeRepositoryInterface::class)->findActiveByUserUuid($userId));
        self::assertNotNull($this->getService(OAuthSessionContext::class)->pendingEmailContext($session));
        self::assertSame('/organizations', $this->getService(EmailVerificationVerifyHandler::class)->handle($request)->getHeaderLine('Location'));
        self::assertCount(1, $this->getService(UserOAuthAccountRepositoryInterface::class)->findAllByUserId($userId));
    }

    #[Test]
    public function startingPasswordRegistrationDiscardsThePreviousOAuthContinuation(): void
    {
        [$session, $request, $oldUserId] = $this->submitEmail();
        $registerRequest                 = new RegisterRequest('password-' . bin2hex(random_bytes(6)) . '@example.test', 'Password User', 'Password1!', 'Password1!');
        $request                         = $request->withAttribute(RegisterRequest::class, $registerRequest);
        self::assertSame('/auth/verification', $this->getService(RegisterHandler::class)->handle($request)->getHeaderLine('Location'));
        self::assertNotSame($oldUserId, new PendingEmailVerificationSession()->userUuid($session));
        self::assertNull($this->getService(OAuthSessionContext::class)->pendingEmailContext($session));
        self::assertFalse($this->getService(OAuthSessionContext::class)->continuationRequired($session));
    }

    #[Test]
    public function startingPasswordLoginDiscardsThePreviousOAuthContinuation(): void
    {
        [$session, $request, $oldUserId] = $this->submitEmail();
        $user                            = $this->getService(IdentityApiFacadeInterface::class)->createUser('password-' . bin2hex(random_bytes(6)) . '@example.test', 'Password User', 'Password1!');
        $request                         = $request->withAttribute(LoginRequest::class, new LoginRequest($user->email, 'Password1!'));
        self::assertSame('/auth/verification', $this->getService(LoginHandler::class)->handle($request)->getHeaderLine('Location'));
        self::assertSame($user->id, new PendingEmailVerificationSession()->userUuid($session));
        self::assertNotSame($oldUserId, $user->id);
        self::assertNull($this->getService(OAuthSessionContext::class)->pendingEmailContext($session));
        self::assertFalse($this->getService(OAuthSessionContext::class)->continuationRequired($session));
    }

    /** @return array{InMemorySession, ServerRequest, string} */
    private function submitEmail(): array
    {
        $inMemorySession = new InMemorySession();
        $context         = $this->getService(OAuthSessionContext::class);
        $context->storePendingEmailContext($inMemorySession, PendingOAuthEmailContext::fromOAuth(OAuthProvider::Codeberg, new OAuthTokenResponse('access-token', null, null), new OAuthUserData(bin2hex(random_bytes(6)), null, 'user', 'OAuth User', null), ''));
        $serverRequest = new ServerRequest()->withAttribute('session', $inMemorySession)->withAttribute(SessionInterface::class, $inMemorySession)->withAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE, new TestInertia())->withAttribute(OAuthEmailRequest::class, new OAuthEmailRequest('oauth-' . bin2hex(random_bytes(6)) . '@example.test'));
        self::assertSame('/auth/verification', $this->getService(OAuthEmailSubmitHandler::class)->handle($serverRequest)->getHeaderLine('Location'));
        $userId = new PendingEmailVerificationSession()->userUuid($inMemorySession);
        self::assertNotNull($userId);
        $repository = $this->getService(EmailVerificationCodeRepositoryInterface::class);
        $code       = $repository->findActiveByUserUuid($userId) ?? new EmailVerificationCode(userUuid: $userId);
        $code->setCodeHash(password_hash('123456', PASSWORD_DEFAULT));
        $code->setExpiresAt(new Chronos('+10 minutes'));
        $repository->save($code);

        return [$inMemorySession, $serverRequest, $userId];
    }
}
