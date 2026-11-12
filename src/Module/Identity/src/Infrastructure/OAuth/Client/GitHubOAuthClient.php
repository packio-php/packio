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

final readonly class GitHubOAuthClient implements OAuthClientInterface
{
    private const array DEFAULT_SCOPES        = ['read:user', 'user:email'];
    private const array DEFAULT_SOURCE_SCOPES = ['read:user', 'user:email', 'repo', 'read:org'];

    private string $baseUrl;

    /**
     * @param array<string, mixed> $githubOAuthConfig
     */
    public function __construct(
        private BrandingConfig $brandingConfig,
        private array $githubOAuthConfig,
        private OAuthHttpClient $oAuthHttpClient,
        private OAuthScopeResolver $oAuthScopeResolver,
        private OAuthTokenResponseMapper $oAuthTokenResponseMapper,
    ) {
        $this->baseUrl = rtrim((string) ($this->githubOAuthConfig['base_url'] ?? 'https://github.com'), '/');
    }

    /**
     * @return list<string>
     */
    public function sourceScopes(): array
    {
        return $this->oAuthScopeResolver->sourceScopes($this->githubOAuthConfig, self::DEFAULT_SOURCE_SCOPES);
    }

    /**
     * @param list<string> $additionalScopes
     */
    public function getAuthorizationUrl(string $state, array $additionalScopes = []): string
    {
        return $this->buildAuthorizationUrl(
            $state,
            $this->oAuthScopeResolver->authorizationScopes($this->githubOAuthConfig, self::DEFAULT_SCOPES, $additionalScopes),
        );
    }

    /**
     * @param list<string> $scopes
     */
    public function getAuthorizationUrlForScopes(string $state, array $scopes): string
    {
        if ([] === $scopes) {
            return $this->getAuthorizationUrl($state);
        }

        return $this->buildAuthorizationUrl($state, $this->oAuthScopeResolver->unique($scopes));
    }

    public function exchangeCodeForToken(string $code, ?string $codeVerifier = null): OAuthTokenResponse
    {
        unset($codeVerifier);

        $tokenUrl = $this->resolveTokenUrl();

        $payload = [
            'client_id'     => $this->githubOAuthConfig['client_id'] ?? '',
            'client_secret' => $this->githubOAuthConfig['client_secret'] ?? '',
            'code'          => $code,
            'redirect_uri'  => $this->githubOAuthConfig['redirect_url'] ?? '',
        ];

        $response = $this->postRequest($tokenUrl, $payload);

        return $this->oAuthTokenResponseMapper->mapOAuthError(
            $response,
            'Failed to exchange code for token: invalid response',
            'OAuth error',
            'Failed to exchange code for token',
        );
    }

    public function fetchUser(string $accessToken): OAuthUserData
    {
        $userUrl = $this->resolveUserUrl();

        $headers = [
            'Accept'               => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent'           => $this->brandingConfig->httpUserAgent(),
            'Authorization'        => sprintf('Bearer %s', $accessToken),
        ];

        $response = $this->oAuthHttpClient->getJson($userUrl, $headers);
        if (200 !== $response['status'] || ! is_array($response['body'])) {
            throw new RuntimeException('Failed to fetch user from GitHub');
        }

        $userId = $response['body']['id'] ?? null;
        if (! is_int($userId) && ! is_string($userId)) {
            throw new RuntimeException('GitHub user response missing id field');
        }

        $login     = is_string($response['body']['login'] ?? null) ? $response['body']['login'] : null;
        $name      = is_string($response['body']['name'] ?? null) ? $response['body']['name'] : null;
        $email     = is_string($response['body']['email'] ?? null) ? $response['body']['email'] : null;
        $avatarUrl = is_string($response['body']['avatar_url'] ?? null) ? $response['body']['avatar_url'] : null;

        $email ??= $this->fetchUserEmail($accessToken);

        return new OAuthUserData(
            providerUserId: (string) $userId,
            email: $email,
            username: $login,
            displayName: $name,
            avatarUrl: $avatarUrl,
        );
    }

    public function refreshToken(string $refreshToken): OAuthTokenResponse
    {
        $tokenUrl = $this->resolveTokenUrl();

        $payload = [
            'client_id'     => $this->githubOAuthConfig['client_id'] ?? '',
            'client_secret' => $this->githubOAuthConfig['client_secret'] ?? '',
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        $response = $this->postRequest($tokenUrl, $payload);

        return $this->oAuthTokenResponseMapper->mapRequiredAccessToken(
            $response,
            'Failed to refresh token: invalid response',
            'Failed to refresh token: no access token returned',
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

    private function resolveAuthorizationUrl(): string
    {
        return $this->nonEmptyConfigString('authorization_url') ?? $this->baseUrl . '/login/oauth/authorize';
    }

    private function resolveTokenUrl(): string
    {
        return $this->nonEmptyConfigString('token_url') ?? $this->baseUrl . '/login/oauth/access_token';
    }

    private function resolveUserUrl(): string
    {
        return $this->nonEmptyConfigString('user_url') ?? 'https://api.github.com/user';
    }

    private function nonEmptyConfigString(string $key): ?string
    {
        $value = $this->githubOAuthConfig[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }

    /**
     * @param list<string> $scopes
     */
    private function buildAuthorizationUrl(string $state, array $scopes): string
    {
        $params = [
            'client_id'    => $this->githubOAuthConfig['client_id'] ?? '',
            'redirect_uri' => $this->githubOAuthConfig['redirect_url'] ?? '',
            'scope'        => implode(',', $scopes),
            'state'        => $state,
        ];

        return sprintf(
            '%s?%s',
            $this->resolveAuthorizationUrl(),
            http_build_query($params),
        );
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

    private function fetchUserEmail(string $accessToken): ?string
    {
        $emailsUrl = 'https://api.github.com/user/emails';

        $headers = [
            'Accept'               => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent'           => $this->brandingConfig->httpUserAgent(),
            'Authorization'        => sprintf('Bearer %s', $accessToken),
        ];

        $response = $this->oAuthHttpClient->getJson($emailsUrl, $headers);
        if (200 !== $response['status'] || ! is_array($response['body'])) {
            return null;
        }

        foreach ($response['body'] as $emailData) {
            if (! is_array($emailData)) {
                continue;
            }

            $email    = $emailData['email'] ?? null;
            $verified = $emailData['verified'] ?? false;
            $primary  = $emailData['primary'] ?? false;

            if (is_string($email) && $verified && $primary) {
                return $email;
            }
        }

        foreach ($response['body'] as $emailData) {
            if (! is_array($emailData)) {
                continue;
            }

            $email    = $emailData['email'] ?? null;
            $verified = $emailData['verified'] ?? false;

            if (is_string($email) && $verified) {
                return $email;
            }
        }

        return null;
    }
}
