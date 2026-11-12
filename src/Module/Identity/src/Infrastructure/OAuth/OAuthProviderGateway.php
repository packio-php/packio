<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth;

use Packio\Identity\Application\Command\OAuth\OAuthProviderInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;

final readonly class OAuthProviderGateway implements OAuthProviderInterface
{
    public function __construct(private OAuthClientFactory $oauthClientFactory) {}

    public function exchangeCodeForToken(OAuthProvider $oAuthProvider, string $code, ?string $codeVerifier = null): OAuthTokenResponse
    {
        return $this->oauthClientFactory->exchangeCodeForToken($oAuthProvider, $code, $codeVerifier);
    }

    public function fetchUser(OAuthProvider $oAuthProvider, string $accessToken): OAuthUserData
    {
        return $this->oauthClientFactory->fetchUser($oAuthProvider, $accessToken);
    }
}
