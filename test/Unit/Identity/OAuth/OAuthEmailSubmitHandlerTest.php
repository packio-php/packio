<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Common\Security\SecretEncryptorInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\SessionInterface;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Security\Http\ClientIpResolverInterface;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Application\Security\Turnstile\TurnstileRequestGuard;
use Packio\Application\Security\Turnstile\TurnstileVerifierInterface;
use Packio\Application\Service\SafeRedirectTarget;
use Packio\Identity\Application\Command\OAuth\OAuthContinuationInterface;
use Packio\Identity\Application\Query\IdentityQueryService;
use Packio\Identity\Application\Security\RegistryAccessTokenSecrets;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use Packio\Identity\Infrastructure\Dto\PendingOAuthEmailContext;
use Packio\Identity\Infrastructure\Handler\OAuthEmailSubmitHandler;
use Packio\Identity\Infrastructure\Handler\OAuthOutcomePresenter;
use Packio\Identity\Infrastructure\Support\OAuthSessionContext;
use PackioTest\Support\Fake\InMemorySession;
use PackioTest\Support\Fake\SecretEncryptorStub;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

#[CoversClass(OAuthEmailSubmitHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class OAuthEmailSubmitHandlerTest extends TestCase
{
    #[Test]
    public function repeatedSubmitRedirectsToVerificationWithoutCreatingAnotherUser(): void
    {
        $inMemorySession     = new InMemorySession();
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

        $workflow = $this->createMock(OAuthContinuationInterface::class);
        $workflow->expects(self::never())->method('submit');

        $oAuthEmailSubmitHandler = new OAuthEmailSubmitHandler(
            $oAuthSessionContext,
            $workflow,
            new OAuthOutcomePresenter($this->createMock(AuthManagerInterface::class), $oAuthSessionContext, new PendingEmailVerificationSession(), new InertiaNotificationMessenger(), $this->identityQueryService()),
            new SafeRedirectTarget(),
            $this->disabledTurnstileGuard(),
            new InertiaNotificationMessenger(),
        );

        $response = $oAuthEmailSubmitHandler->handle(
            new ServerRequest()
                ->withAttribute('session', $inMemorySession)
                ->withAttribute(SessionInterface::class, $inMemorySession),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/auth/verification', $response->getHeaderLine('Location'));
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
