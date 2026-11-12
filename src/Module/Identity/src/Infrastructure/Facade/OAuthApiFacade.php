<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Facade;

use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Infrastructure\OAuth\OAuthClientFactory;
use Packio\Identity\Infrastructure\OAuth\OAuthProviderAvailabilityResolver;

final readonly class OAuthApiFacade implements OAuthApiFacadeInterface
{
    public function __construct(
        private OAuthClientFactory $oauthClientFactory,
        private OAuthProviderAvailabilityResolver $oauthProviderAvailabilityResolver,
    ) {}

    public function providerAvailabilities(): array
    {
        return $this->oauthProviderAvailabilityResolver->all();
    }

    public function gitLabSelfHostedSourceConnectEnabled(): bool
    {
        return $this->oauthProviderAvailabilityResolver->gitLabSelfHostedSourceConnectEnabled();
    }

    public function getAuthorizationUrl(string $provider, string $state, ?string $codeChallenge = null): string
    {
        return $this->oauthClientFactory->getAuthorizationUrl(
            OAuthProvider::from($provider),
            $state,
            codeChallenge: $codeChallenge,
        );
    }

    public function getSourceAuthorizationUrl(string $provider, string $state, ?string $codeChallenge = null): string
    {
        return $this->oauthClientFactory->getSourceAuthorizationUrl(
            OAuthProvider::from($provider),
            $state,
            $codeChallenge,
        );
    }

    public function getSourceAuthorizationUrlForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $state,
        array $scopes,
        ?string $codeChallenge = null
    ): string {
        unset($codeChallenge);

        return $this->oauthClientFactory
            ->getProviderInstanceClient($provider, $providerInstanceConfig)
            ->getAuthorizationUrlForScopes($state, $scopes)
        ;
    }

    public function exchangeSourceCodeForToken(string $provider, string $code, ?string $codeVerifier = null): array
    {
        $oAuthTokenResponse = $this->oauthClientFactory->exchangeSourceCodeForToken(
            OAuthProvider::from($provider),
            $code,
            $codeVerifier,
        );

        return [
            'access_token'  => $oAuthTokenResponse->accessToken,
            'refresh_token' => $oAuthTokenResponse->refreshToken,
            'expires_in'    => $oAuthTokenResponse->expiresIn,
        ];
    }

    public function exchangeCodeForTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $code,
        ?string $codeVerifier = null
    ): array {
        $oAuthTokenResponse = $this->oauthClientFactory
            ->getProviderInstanceClient($provider, $providerInstanceConfig)
            ->exchangeCodeForToken($code, $codeVerifier)
        ;

        return [
            'access_token'  => $oAuthTokenResponse->accessToken,
            'refresh_token' => $oAuthTokenResponse->refreshToken,
            'expires_in'    => $oAuthTokenResponse->expiresIn,
        ];
    }

    public function fetchSourceUser(string $provider, string $accessToken): array
    {
        $oAuthUserData = $this->oauthClientFactory->fetchSourceUser(
            OAuthProvider::from($provider),
            $accessToken,
        );

        return [
            'provider_user_id' => $oAuthUserData->providerUserId,
            'email'            => $oAuthUserData->email,
            'username'         => $oAuthUserData->username,
            'display_name'     => $oAuthUserData->displayName,
            'avatar_url'       => $oAuthUserData->avatarUrl,
        ];
    }

    public function fetchUserForProviderInstance(string $provider, array $providerInstanceConfig, string $accessToken): array
    {
        $oAuthUserData = $this->oauthClientFactory
            ->getProviderInstanceClient($provider, $providerInstanceConfig)
            ->fetchUser($accessToken)
        ;

        return [
            'provider_user_id' => $oAuthUserData->providerUserId,
            'email'            => $oAuthUserData->email,
            'username'         => $oAuthUserData->username,
            'display_name'     => $oAuthUserData->displayName,
            'avatar_url'       => $oAuthUserData->avatarUrl,
        ];
    }

    public function sourceScopes(string $provider): array
    {
        return $this->oauthClientFactory->getSourceScopes(
            OAuthProvider::from($provider),
        );
    }

    public function verifySourceToken(string $provider, string $accessToken): bool
    {
        return $this->oauthClientFactory->verifySourceToken(
            OAuthProvider::from($provider),
            $accessToken,
        );
    }

    public function refreshSourceAccessToken(string $provider, string $refreshToken): array
    {
        $oAuthTokenResponse = $this->oauthClientFactory->refreshSourceToken(
            OAuthProvider::from($provider),
            $refreshToken,
        );

        return [
            'access_token'  => $oAuthTokenResponse->accessToken,
            'refresh_token' => $oAuthTokenResponse->refreshToken,
            'expires_in'    => $oAuthTokenResponse->expiresIn,
        ];
    }

    public function refreshAccessTokenForProviderInstance(string $provider, array $providerInstanceConfig, string $refreshToken): array
    {
        $oAuthTokenResponse = $this->oauthClientFactory
            ->getProviderInstanceClient($provider, $providerInstanceConfig)
            ->refreshToken($refreshToken)
        ;

        return [
            'access_token'  => $oAuthTokenResponse->accessToken,
            'refresh_token' => $oAuthTokenResponse->refreshToken,
            'expires_in'    => $oAuthTokenResponse->expiresIn,
        ];
    }
}
