<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth;

use Common\Branding\BrandingConfig;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use Packio\Identity\Infrastructure\OAuth\Client\BitbucketOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\CodebergOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\GitHubOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\GitLabOAuthClient;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthHttpClient;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthScopeResolver;
use Packio\Identity\Infrastructure\OAuth\Client\OAuthTokenResponseMapper;
use RuntimeException;

final readonly class OAuthClientFactory
{
    public function __construct(
        private GitHubOAuthClient $gitHubOAuthClient,
        private GitLabOAuthClient $gitLabOAuthClient,
        private BitbucketOAuthClient $bitbucketOAuthClient,
        private CodebergOAuthClient $codebergOAuthClient,
        private BrandingConfig $brandingConfig,
        private OAuthClientConfigResolver $oauthClientConfigResolver,
        private OAuthHttpClient $oAuthHttpClient,
        private OAuthScopeResolver $oAuthScopeResolver,
        private OAuthTokenResponseMapper $oAuthTokenResponseMapper,
    ) {}

    public function getClient(OAuthProvider $oAuthProvider): OAuthClientInterface
    {
        return match ($oAuthProvider) {
            OAuthProvider::Github    => $this->gitHubOAuthClient,
            OAuthProvider::Gitlab    => $this->gitLabOAuthClient,
            OAuthProvider::Bitbucket => $this->bitbucketOAuthClient,
            OAuthProvider::Codeberg  => $this->codebergOAuthClient,
        };
    }

    public function getSourceClient(OAuthProvider $oAuthProvider): OAuthClientInterface
    {
        $sourceConfig = $this->oauthClientConfigResolver->sourceConfig($oAuthProvider->value);

        return match ($oAuthProvider) {
            OAuthProvider::Github    => new GitHubOAuthClient($this->brandingConfig, $sourceConfig, $this->oAuthHttpClient, $this->oAuthScopeResolver, $this->oAuthTokenResponseMapper),
            OAuthProvider::Gitlab    => new GitLabOAuthClient($this->brandingConfig, $sourceConfig, $this->oAuthHttpClient, $this->oAuthScopeResolver, $this->oAuthTokenResponseMapper),
            OAuthProvider::Bitbucket => new BitbucketOAuthClient($this->brandingConfig, $sourceConfig, $this->oAuthHttpClient, $this->oAuthScopeResolver, $this->oAuthTokenResponseMapper),
            OAuthProvider::Codeberg  => new CodebergOAuthClient($this->brandingConfig, $sourceConfig, $this->oAuthHttpClient, $this->oAuthScopeResolver, $this->oAuthTokenResponseMapper),
        };
    }

    /**
     * @param list<string> $additionalScopes
     */
    public function getAuthorizationUrl(
        OAuthProvider $oAuthProvider,
        string $state,
        array $additionalScopes = [],
        ?string $codeChallenge = null
    ): string {
        if (OAuthProvider::Codeberg === $oAuthProvider && null !== $codeChallenge) {
            return $this->codebergOAuthClient->getAuthorizationUrlWithPkce($state, $codeChallenge, $additionalScopes);
        }

        return $this->getClient($oAuthProvider)->getAuthorizationUrl($state, $additionalScopes);
    }

    public function getSourceAuthorizationUrl(OAuthProvider $oAuthProvider, string $state, ?string $codeChallenge = null): string
    {
        $oAuthClient = $this->getSourceClient($oAuthProvider);
        $scopes      = $this->getSourceScopes($oAuthProvider);

        if (OAuthProvider::Codeberg === $oAuthProvider && null !== $codeChallenge) {
            if (! $oAuthClient instanceof CodebergOAuthClient) {
                throw new RuntimeException('Codeberg OAuth client is not available.');
            }

            return $oAuthClient->getAuthorizationUrlForScopesWithPkce($state, $scopes, $codeChallenge);
        }

        return $oAuthClient->getAuthorizationUrlForScopes($state, $scopes);
    }

    /**
     * @return list<string>
     */
    public function getSourceScopes(OAuthProvider $oAuthProvider): array
    {
        $oAuthClient = $this->getSourceClient($oAuthProvider);

        return match (true) {
            $oAuthClient instanceof GitHubOAuthClient    => $oAuthClient->sourceScopes(),
            $oAuthClient instanceof GitLabOAuthClient    => $oAuthClient->sourceScopes(),
            $oAuthClient instanceof BitbucketOAuthClient => $oAuthClient->sourceScopes(),
            $oAuthClient instanceof CodebergOAuthClient  => $oAuthClient->sourceScopes(),
            default                                      => [],
        };
    }

    public function exchangeCodeForToken(OAuthProvider $oAuthProvider, string $code, ?string $codeVerifier = null): OAuthTokenResponse
    {
        return $this->getClient($oAuthProvider)->exchangeCodeForToken($code, $codeVerifier);
    }

    public function exchangeSourceCodeForToken(OAuthProvider $oAuthProvider, string $code, ?string $codeVerifier = null): OAuthTokenResponse
    {
        return $this->getSourceClient($oAuthProvider)->exchangeCodeForToken($code, $codeVerifier);
    }

    public function fetchUser(OAuthProvider $oAuthProvider, string $accessToken): OAuthUserData
    {
        return $this->getClient($oAuthProvider)->fetchUser($accessToken);
    }

    public function fetchSourceUser(OAuthProvider $oAuthProvider, string $accessToken): OAuthUserData
    {
        return $this->getSourceClient($oAuthProvider)->fetchUser($accessToken);
    }

    public function refreshSourceToken(OAuthProvider $oAuthProvider, string $refreshToken): OAuthTokenResponse
    {
        return $this->getSourceClient($oAuthProvider)->refreshToken($refreshToken);
    }

    public function verifySourceToken(OAuthProvider $oAuthProvider, string $accessToken): bool
    {
        return $this->getSourceClient($oAuthProvider)->verifyToken($accessToken);
    }

    /**
     * @param array<string, mixed> $providerInstanceConfig
     */
    public function getProviderInstanceClient(string $provider, array $providerInstanceConfig): OAuthClientInterface
    {
        return match ($provider) {
            'gitlab_self_hosted' => new GitLabOAuthClient($this->brandingConfig, $providerInstanceConfig, $this->oAuthHttpClient, $this->oAuthScopeResolver, $this->oAuthTokenResponseMapper),
            default              => throw new RuntimeException('OAuth provider instance is not supported.'),
        };
    }
}
