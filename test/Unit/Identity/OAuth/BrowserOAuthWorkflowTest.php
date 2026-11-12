<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\OAuth;

use Packio\Identity\Application\Command\OAuth\BrowserOAuthWorkflow;
use Packio\Identity\Application\Command\OAuth\OAuthAccountLinkerInterface;
use Packio\Identity\Application\Command\OAuth\OAuthCallbackTransactionInterface;
use Packio\Identity\Application\Command\OAuth\OAuthCallbackUseCase;
use Packio\Identity\Application\Command\OAuth\OAuthProviderInterface;
use Packio\Identity\Application\Command\OAuth\OAuthRegistrationPolicyInterface;
use Packio\Identity\Application\Command\OAuth\OAuthUserResolverInterface;
use Packio\Identity\Application\Result\OAuthCallbackResult;
use Packio\Identity\Application\Result\OAuthCallbackStatus;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrowserOAuthWorkflow::class)]
final class BrowserOAuthWorkflowTest extends TestCase
{
    #[Test]
    public function exchangesPkceCodeAndPreservesProviderIdentityForEmailContinuation(): void
    {
        $oAuthTokenResponse   = new OAuthTokenResponse('access', 'refresh', 600);
        $oAuthUserData        = new OAuthUserData('123', null, 'username', 'Name', null);
        $provider             = $this->createMock(OAuthProviderInterface::class);
        $provider->expects(self::once())->method('exchangeCodeForToken')->with(OAuthProvider::Codeberg, 'code', 'verifier')->willReturn($oAuthTokenResponse);
        $provider->expects(self::once())->method('fetchUser')->with(OAuthProvider::Codeberg, 'access')->willReturn($oAuthUserData);
        $accounts = $this->createMock(UserOAuthAccountRepositoryInterface::class);
        $accounts->expects(self::once())->method('findByProviderAndProviderUserId')->with(OAuthProvider::Codeberg, '123')->willReturn(null);
        $transaction = $this->createMock(OAuthCallbackTransactionInterface::class);
        $transaction->expects(self::once())->method('run')->willReturnCallback(static fn (callable $callback): OAuthCallbackResult => $callback());
        $oAuthCallbackUseCase  = new OAuthCallbackUseCase($this->createStub(UserRepositoryInterface::class), $accounts, $this->createStub(OAuthRegistrationPolicyInterface::class), $this->createStub(OAuthUserResolverInterface::class), $this->createStub(OAuthAccountLinkerInterface::class), $transaction);
        $oAuthCallbackResult   = new BrowserOAuthWorkflow($provider, $oAuthCallbackUseCase)->handle('codeberg', 'code', '/organization/invite/token', 'verifier');
        self::assertSame(OAuthCallbackStatus::EmailRequired, $oAuthCallbackResult->status);
        self::assertNotNull($oAuthCallbackResult->pendingOAuth);
        self::assertSame($oAuthTokenResponse, $oAuthCallbackResult->pendingOAuth->tokens);
        self::assertSame($oAuthUserData, $oAuthCallbackResult->pendingOAuth->userData);
        self::assertSame('/organization/invite/token', $oAuthCallbackResult->pendingOAuth->registrationRedirectTarget);
    }
}
