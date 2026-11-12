<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command\OAuth;

use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;

interface OAuthProviderInterface
{
    public function exchangeCodeForToken(OAuthProvider $oAuthProvider, string $code, ?string $codeVerifier = null): OAuthTokenResponse;

    public function fetchUser(OAuthProvider $oAuthProvider, string $accessToken): OAuthUserData;
}
