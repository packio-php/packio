<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth\Client;

use Common\Branding\BrandingConfig;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use Packio\Identity\Infrastructure\OAuth\OAuthClientInterface;
use RuntimeException;

use function http_build_query;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function rtrim;
use function sprintf;
use function trim;

final readonly class GitLabOAuthClient implements OAuthClientInterface
{
    private const array DEFAULT_SCOPES        = ['read_user', 'openid', 'profile'];
    private const array DEFAULT_SOURCE_SCOPES = ['read_user', 'api', 'read_repository'];

    private string $baseUrl;

    /**
     * @param array<string, mixed> $gitlabOAuthConfig
     */
    public function __construct(
        private BrandingConfig $brandingConfig,
        private array $gitlabOAuthConfig,
        private OAuthHttpClient $oAuthHttpClient,
        private OAuthScopeResolver $oAuthScopeResolver,
        private OAuthTokenResponseMapper $oAuthTokenResponseMapper,
    ) {
        $this->baseUrl = rtrim((string) ($this->gitlabOAuthConfig['base_url'] ?? 'https://gitlab.com'), '/');
    }

    /**
     * @return list<string>
     */
    public function sourceScopes(): array
    {
        return $this->oAuthScopeResolver->sourceScopes($this->gitlabOAuthConfig, self::DEFAULT_SOURCE_SCOPES);
    }

    public function getAuthorizationUrl(string $state, array $additionalScopes = []): string
    {
        return $this->buildAuthorizationUrl(
            $state,
            $this->oAuthScopeResolver->authorizationScopes($this->gitlabOAuthConfig, self::DEFAULT_SCOPES, $additionalScopes),
            (string) ($this->gitlabOAuthConfig['redirect_url'] ?? ''),
        );
    }

    public function getAuthorizationUrlForScopes(string $state, array $scopes): string
    {
        if ([] === $scopes) {
            return $this->getAuthorizationUrl($state);
        }

        return $this->buildAuthorizationUrl(
            $state,
            $this->oAuthScopeResolver->unique($scopes),
            (string) ($this->gitlabOAuthConfig['redirect_url'] ?? ''),
        );
    }

    public function exchangeCodeForToken(string $code, ?string $codeVerifier = null): OAuthTokenResponse
    {
        unset($codeVerifier);

        $response = $this->postRequest(
            $this->resolveTokenUrl(),
            [
                'client_id'     => $this->gitlabOAuthConfig['client_id'] ?? '',
                'client_secret' => $this->gitlabOAuthConfig['client_secret'] ?? '',
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $this->gitlabOAuthConfig['redirect_url'] ?? '',
            ],
        );

        return $this->oAuthTokenResponseMapper->mapOAuthError(
            $response,
            'Failed to exchange code for GitLab token: invalid response',
            'GitLab OAuth error',
            'Failed to exchange code for token',
        );
    }

    public function fetchUser(string $accessToken): OAuthUserData
    {
        $response = $this->oAuthHttpClient->getJson(
            $this->resolveUserUrl(),
            [
                'Accept'        => 'application/json',
                'User-Agent'    => $this->brandingConfig->httpUserAgent(),
                'Authorization' => sprintf('Bearer %s', $accessToken),
            ],
        );

        if (200 !== $response['status'] || ! is_array($response['body'])) {
            throw new RuntimeException('Failed to fetch user from GitLab');
        }

        $userId = $response['body']['id'] ?? null;
        if (! is_int($userId) && ! is_string($userId)) {
            throw new RuntimeException('GitLab user response missing id field');
        }

        return new OAuthUserData(
            providerUserId: (string) $userId,
            email: is_string($response['body']['email'] ?? null) && is_string($response['body']['confirmed_at'] ?? null) && '' !== $response['body']['confirmed_at'] ? $response['body']['email'] : null,
            username: is_string($response['body']['username'] ?? null) ? $response['body']['username'] : null,
            displayName: is_string($response['body']['name'] ?? null) ? $response['body']['name'] : null,
            avatarUrl: is_string($response['body']['avatar_url'] ?? null) ? $response['body']['avatar_url'] : null,
        );
    }

    public function refreshToken(string $refreshToken): OAuthTokenResponse
    {
        $response = $this->postRequest(
            $this->resolveTokenUrl(),
            [
                'client_id'     => $this->gitlabOAuthConfig['client_id'] ?? '',
                'client_secret' => $this->gitlabOAuthConfig['client_secret'] ?? '',
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
            ],
        );

        return $this->oAuthTokenResponseMapper->mapRequiredAccessToken(
            $response,
            'Failed to refresh GitLab token: invalid response',
            'Failed to refresh GitLab token: no access token returned',
        );
    }

    public function verifyToken(string $accessToken): bool
    {
        try {
            $this->fetchUser($accessToken);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @param list<string> $scopes
     */
    private function buildAuthorizationUrl(string $state, array $scopes, string $redirectUri): string
    {
        $params = [
            'client_id'     => $this->gitlabOAuthConfig['client_id'] ?? '',
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => implode(' ', $scopes),
            'state'         => $state,
        ];

        return sprintf(
            '%s?%s',
            $this->resolveAuthorizationUrl(),
            http_build_query($params),
        );
    }

    private function resolveAuthorizationUrl(): string
    {
        return $this->nonEmptyConfigString('authorization_url') ?? $this->baseUrl . '/oauth/authorize';
    }

    private function resolveTokenUrl(): string
    {
        return $this->nonEmptyConfigString('token_url') ?? $this->baseUrl . '/oauth/token';
    }

    private function resolveUserUrl(): string
    {
        return $this->nonEmptyConfigString('user_url') ?? $this->baseUrl . '/api/v4/user';
    }

    private function nonEmptyConfigString(string $key): ?string
    {
        $value = $this->gitlabOAuthConfig[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{status: int, body: null|array<int|string, mixed>}
     */
    private function postRequest(string $url, array $payload): array
    {
        return $this->oAuthHttpClient->postForm(
            $url,
            $payload,
            [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        );
    }
}
