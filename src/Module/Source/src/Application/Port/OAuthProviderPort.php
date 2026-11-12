<?php

declare(strict_types=1);

namespace Packio\Source\Application\Port;

use Packio\Source\Application\Dto\OAuthTokenData;
use Packio\Source\Application\Dto\OAuthUserData;

interface OAuthProviderPort
{
    public function exchangeCodeForToken(string $provider, string $code, ?string $codeVerifier = null): OAuthTokenData;

    /**
     * @param array<string, mixed> $providerInstanceConfig
     */
    public function exchangeCodeForTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $code,
        ?string $codeVerifier = null
    ): OAuthTokenData;

    public function fetchUser(string $provider, string $accessToken): OAuthUserData;

    /**
     * @param array<string, mixed> $providerInstanceConfig
     */
    public function fetchUserForProviderInstance(string $provider, array $providerInstanceConfig, string $accessToken): OAuthUserData;

    /**
     * @return list<string>
     */
    public function sourceScopes(string $provider): array;

    public function verifyToken(string $provider, string $accessToken): bool;

    public function refreshAccessToken(string $provider, string $refreshToken): OAuthTokenData;

    /**
     * @param array<string, mixed> $providerInstanceConfig
     */
    public function refreshAccessTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $refreshToken
    ): OAuthTokenData;

    /**
     * @param array<string, mixed> $providerInstanceConfig
     * @param list<string>         $scopes
     */
    public function getAuthorizationUrlForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $state,
        array $scopes,
        ?string $codeChallenge = null
    ): string;

    /**
     * Keyed by source provider; the first entry wins when several OAuth providers map to the same source provider.
     *
     * @return array<string, array{provider: string, available: bool}>
     */
    public function sourceConnectAvailability(): array;
}
