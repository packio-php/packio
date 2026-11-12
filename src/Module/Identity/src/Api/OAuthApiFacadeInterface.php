<?php

declare(strict_types=1);

namespace Packio\Identity\Api;

use Packio\Identity\Api\Dto\OAuthProviderAvailability;

interface OAuthApiFacadeInterface
{
    /** @return list<OAuthProviderAvailability> */
    public function providerAvailabilities(): array;

    public function gitLabSelfHostedSourceConnectEnabled(): bool;

    public function getAuthorizationUrl(string $provider, string $state, ?string $codeChallenge = null): string;

    public function getSourceAuthorizationUrl(string $provider, string $state, ?string $codeChallenge = null): string;

    /**
     * @param array<string, mixed> $providerInstanceConfig
     * @param list<string>         $scopes
     */
    public function getSourceAuthorizationUrlForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $state,
        array $scopes,
        ?string $codeChallenge = null
    ): string;

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int}
     */
    public function exchangeSourceCodeForToken(string $provider, string $code, ?string $codeVerifier = null): array;

    /**
     * @param array<string, mixed> $providerInstanceConfig
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int}
     */
    public function exchangeCodeForTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $code,
        ?string $codeVerifier = null
    ): array;

    /**
     * @return array{provider_user_id: string, email: ?string, username: ?string, display_name: ?string, avatar_url: ?string}
     */
    public function fetchSourceUser(string $provider, string $accessToken): array;

    /**
     * @param array<string, mixed> $providerInstanceConfig
     *
     * @return array{provider_user_id: string, email: ?string, username: ?string, display_name: ?string, avatar_url: ?string}
     */
    public function fetchUserForProviderInstance(string $provider, array $providerInstanceConfig, string $accessToken): array;

    /**
     * @return list<string>
     */
    public function sourceScopes(string $provider): array;

    public function verifySourceToken(string $provider, string $accessToken): bool;

    /**
     * Refreshes a source OAuth access token using the source OAuth client config.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int}
     */
    public function refreshSourceAccessToken(string $provider, string $refreshToken): array;

    /**
     * @param array<string, mixed> $providerInstanceConfig
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int}
     */
    public function refreshAccessTokenForProviderInstance(string $provider, array $providerInstanceConfig, string $refreshToken): array;
}
