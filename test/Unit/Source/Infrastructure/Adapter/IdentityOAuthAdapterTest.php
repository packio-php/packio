<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Adapter;

use Packio\Identity\Api\Dto\OAuthProviderAvailability;
use Packio\Identity\Api\Exception\OAuthRefreshRejectedException;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Source\Application\Dto\OAuthTokenData;
use Packio\Source\Application\Dto\OAuthUserData;
use Packio\Source\Domain\Exception\ProviderRefreshRejectedException;
use Packio\Source\Infrastructure\Adapter\IdentityOAuthAdapter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdentityOAuthAdapter::class)]
#[AllowMockObjectsWithoutExpectations]
final class IdentityOAuthAdapterTest extends TestCase
{
    #[Test]
    public function exchangeCodeForTokenUsesSourceFacadeExchange(): void
    {
        $facade = $this->createMock(OAuthApiFacadeInterface::class);
        $facade->expects(self::once())
            ->method('exchangeSourceCodeForToken')
            ->with('github', 'code', 'verifier')
            ->willReturn([
                'access_token'  => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in'    => 3600,
            ])
        ;

        $oAuthTokenData = new IdentityOAuthAdapter($facade)->exchangeCodeForToken('github', 'code', 'verifier');

        self::assertInstanceOf(OAuthTokenData::class, $oAuthTokenData);
        self::assertSame('access-token', $oAuthTokenData->accessToken);
    }

    #[Test]
    public function refreshRejectionIsTranslatedToSourceException(): void
    {
        $facade = $this->createStub(OAuthApiFacadeInterface::class);
        $facade->method('refreshSourceAccessToken')->willThrowException(new OAuthRefreshRejectedException('rejected'));

        $this->expectException(ProviderRefreshRejectedException::class);
        $this->expectExceptionMessage('rejected');

        new IdentityOAuthAdapter($facade)->refreshAccessToken('github', 'refresh-token');
    }

    #[Test]
    public function providerInstanceRefreshRejectionIsTranslatedToSourceException(): void
    {
        $facade = $this->createStub(OAuthApiFacadeInterface::class);
        $facade->method('refreshAccessTokenForProviderInstance')->willThrowException(new OAuthRefreshRejectedException('rejected'));

        $this->expectException(ProviderRefreshRejectedException::class);
        $this->expectExceptionMessage('rejected');

        new IdentityOAuthAdapter($facade)->refreshAccessTokenForProviderInstance('gitlab_self_hosted', [], 'refresh-token');
    }

    #[Test]
    public function fetchUserUsesSourceFacadeFetch(): void
    {
        $facade = $this->createMock(OAuthApiFacadeInterface::class);
        $facade->expects(self::once())
            ->method('fetchSourceUser')
            ->with('github', 'access-token')
            ->willReturn([
                'provider_user_id' => 'provider-user',
                'email'            => 'user@example.com',
                'username'         => 'user',
                'display_name'     => 'User',
                'avatar_url'       => 'https://example.com/avatar.png',
            ])
        ;

        $oAuthUserData = new IdentityOAuthAdapter($facade)->fetchUser('github', 'access-token');

        self::assertInstanceOf(OAuthUserData::class, $oAuthUserData);
        self::assertSame('provider-user', $oAuthUserData->providerUserId);
    }

    #[Test]
    public function refreshAccessTokenUsesSourceFacadeRefresh(): void
    {
        $facade = $this->createMock(OAuthApiFacadeInterface::class);
        $facade->expects(self::once())
            ->method('refreshSourceAccessToken')
            ->with('codeberg', 'refresh-token')
            ->willReturn([
                'access_token'  => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in'    => 3600,
            ])
        ;

        $oAuthTokenData = new IdentityOAuthAdapter($facade)->refreshAccessToken('codeberg', 'refresh-token');

        self::assertSame('new-access-token', $oAuthTokenData->accessToken);
        self::assertSame('new-refresh-token', $oAuthTokenData->refreshToken);
    }

    #[Test]
    public function verifyTokenUsesSourceFacadeVerify(): void
    {
        $facade = $this->createMock(OAuthApiFacadeInterface::class);
        $facade->expects(self::once())
            ->method('verifySourceToken')
            ->with('github', 'access-token')
            ->willReturn(true)
        ;

        self::assertTrue(new IdentityOAuthAdapter($facade)->verifyToken('github', 'access-token'));
    }

    #[Test]
    public function sourceConnectAvailabilityKeepsFirstEntryPerSourceProvider(): void
    {
        $facade = $this->createStub(OAuthApiFacadeInterface::class);
        $facade->method('providerAvailabilities')->willReturn([
            new OAuthProviderAvailability(provider: 'github', enabled: true, supportsAuthLogin: true, supportsSourceConnect: true, sourceProvider: 'github', visibleOnAuthPages: true, visibleOnSources: true, sourceConnectEnabled: true),
            new OAuthProviderAvailability(provider: 'github_app', enabled: true, supportsAuthLogin: true, supportsSourceConnect: true, sourceProvider: 'github', visibleOnAuthPages: true, visibleOnSources: true, sourceConnectEnabled: false),
            new OAuthProviderAvailability(provider: 'google', enabled: true, supportsAuthLogin: true, supportsSourceConnect: true, sourceProvider: null, visibleOnAuthPages: true, visibleOnSources: true, sourceConnectEnabled: true),
        ]);

        self::assertSame(
            [
                'github' => [
                    'provider'  => 'github',
                    'available' => true,
                ],
            ],
            new IdentityOAuthAdapter($facade)->sourceConnectAvailability(),
        );
    }
}
