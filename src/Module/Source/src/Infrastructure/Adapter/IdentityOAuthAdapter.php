<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter;

use Packio\Identity\Api\Exception\OAuthRefreshRejectedException;
use Packio\Identity\Api\OAuthApiFacadeInterface;
use Packio\Source\Application\Dto\OAuthTokenData;
use Packio\Source\Application\Dto\OAuthUserData;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Domain\Exception\ProviderRefreshRejectedException;

final readonly class IdentityOAuthAdapter implements OAuthProviderPort
{
    public function __construct(private OAuthApiFacadeInterface $oAuthApiFacade) {}

    public function exchangeCodeForToken(string $provider, string $code, ?string $codeVerifier = null): OAuthTokenData
    {
        $result = $this->oAuthApiFacade->exchangeSourceCodeForToken($provider, $code, $codeVerifier);

        return OAuthTokenData::fromArray($result);
    }

    public function exchangeCodeForTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $code,
        ?string $codeVerifier = null
    ): OAuthTokenData {
        $result = $this->oAuthApiFacade->exchangeCodeForTokenForProviderInstance($provider, $providerInstanceConfig, $code, $codeVerifier);

        return OAuthTokenData::fromArray($result);
    }

    public function fetchUser(string $provider, string $accessToken): OAuthUserData
    {
        $result = $this->oAuthApiFacade->fetchSourceUser($provider, $accessToken);

        return OAuthUserData::fromArray($result);
    }

    public function fetchUserForProviderInstance(string $provider, array $providerInstanceConfig, string $accessToken): OAuthUserData
    {
        $result = $this->oAuthApiFacade->fetchUserForProviderInstance($provider, $providerInstanceConfig, $accessToken);

        return OAuthUserData::fromArray($result);
    }

    /**
     * @return list<string>
     */
    public function sourceScopes(string $provider): array
    {
        return $this->oAuthApiFacade->sourceScopes($provider);
    }

    public function verifyToken(string $provider, string $accessToken): bool
    {
        return $this->oAuthApiFacade->verifySourceToken($provider, $accessToken);
    }

    public function refreshAccessToken(string $provider, string $refreshToken): OAuthTokenData
    {
        try {
            $result = $this->oAuthApiFacade->refreshSourceAccessToken($provider, $refreshToken);
        } catch (OAuthRefreshRejectedException $oAuthRefreshRejectedException) {
            throw new ProviderRefreshRejectedException($oAuthRefreshRejectedException->getMessage(), $oAuthRefreshRejectedException->getCode(), $oAuthRefreshRejectedException);
        }

        return OAuthTokenData::fromArray($result);
    }

    public function refreshAccessTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $refreshToken
    ): OAuthTokenData {
        try {
            $result = $this->oAuthApiFacade->refreshAccessTokenForProviderInstance($provider, $providerInstanceConfig, $refreshToken);
        } catch (OAuthRefreshRejectedException $oAuthRefreshRejectedException) {
            throw new ProviderRefreshRejectedException($oAuthRefreshRejectedException->getMessage(), $oAuthRefreshRejectedException->getCode(), $oAuthRefreshRejectedException);
        }

        return OAuthTokenData::fromArray($result);
    }

    public function getAuthorizationUrlForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $state,
        array $scopes,
        ?string $codeChallenge = null
    ): string {
        return $this->oAuthApiFacade->getSourceAuthorizationUrlForProviderInstance($provider, $providerInstanceConfig, $state, $scopes, $codeChallenge);
    }

    public function sourceConnectAvailability(): array
    {
        $result = [];

        foreach ($this->oAuthApiFacade->providerAvailabilities() as $oAuthProviderAvailability) {
            $key = $oAuthProviderAvailability->sourceProvider;

            if (null === $key || isset($result[$key])) {
                continue;
            }

            $result[$key] = [
                'provider'  => $oAuthProviderAvailability->provider,
                'available' => $oAuthProviderAvailability->isSourceConnectAvailable(),
            ];
        }

        return $result;
    }
}
