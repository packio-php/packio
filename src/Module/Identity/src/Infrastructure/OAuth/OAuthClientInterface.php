<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth;

use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;

interface OAuthClientInterface
{
    /**
     * @param list<string> $additionalScopes
     */
    public function getAuthorizationUrl(string $state, array $additionalScopes = []): string;

    /**
     * @param list<string> $scopes
     */
    public function getAuthorizationUrlForScopes(string $state, array $scopes): string;

    public function exchangeCodeForToken(string $code, ?string $codeVerifier = null): OAuthTokenResponse;

    public function fetchUser(string $accessToken): OAuthUserData;

    public function refreshToken(string $refreshToken): OAuthTokenResponse;

    public function verifyToken(string $accessToken): bool;
}
