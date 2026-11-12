<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Infrastructure\Handler;

use Common\Security\SecretEncryptorInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Security\Turnstile\TurnstileVerifierInterface;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Application\Command\IdentityCredentialsInterface;
use Packio\Identity\Application\Command\SignInWorkflow;
use Packio\Identity\Application\Command\VerificationDispatch;
use Packio\Identity\Application\Query\IdentityQueryService;
use Packio\Identity\Application\Result\IdentityUserResult;
use Packio\Identity\Application\Security\RegistryAccessTokenSecrets;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Infrastructure\Handler\LoginHandler;
use Packio\Identity\Infrastructure\Request\LoginRequest;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

#[CoversClass(LoginHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class LoginHandlerTest extends TestCase
{
    #[Test]
    public function verifiedUserLoginUsesAuthManagerResponse(): void
    {
        $request = new ServerRequest();
        $request = $request->withAttribute(LoginRequest::class, new LoginRequest(
            email: 'user@example.com',
            password: 'Password1!',
            redirectTo: '/organization/invite/orgi_test',
        ));

        $identityApi = $this->createMock(IdentityCredentialsInterface::class);
        $identityApi->expects(self::once())
            ->method('authenticateCredentials')
            ->with('user@example.com', 'Password1!')
            ->willReturn(new IdentityUserResult(
                id: 'user-uuid',
                email: 'user@example.com',
                displayName: 'User',
                roles: ['user'],
                createdAt: '2026-08-13T00:00:00+00:00',
                updatedAt: '',
                hasPassword: true,
                isEmailVerified: true,
            ))
        ;

        $authManager         = $this->createMock(AuthManagerInterface::class);
        $redirectResponse    = new RedirectResponse('/response-from-auth-manager');
        $authManager->expects(self::once())
            ->method('login')
            ->with($request, self::isInstanceOf(RedirectResponse::class), [
                'user_id'                 => 'user-uuid',
                'credentials_fingerprint' => null,
            ])
            ->willReturn($redirectResponse)
        ;

        $loginHandler = new LoginHandler(
            new SignInWorkflow($identityApi, new VerificationDispatch($this->createMock(EventDispatcherInterface::class), $this->createStub(LoggerInterface::class))),
            $authManager,
            new SafeRedirectTarget(),
            $this->disabledTurnstileGuard(),
            $this->identityQueryService(),
            new PendingEmailVerificationSession(),
            new ValidationRedirectState(),
            new OAuthSessionContext(new SecretEncryptorStub()),
        );

        self::assertSame($redirectResponse, $loginHandler->handle($request));
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
