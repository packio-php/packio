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

final readonly class CodebergOAuthClient implements OAuthClientInterface
{
    private const array DEFAULT_SCOPES        = [];
    private const array DEFAULT_SOURCE_SCOPES = [];

    private string $baseUrl;

    /**
     * @param array<string, mixed> $codebergOAuthConfig
     */
    public function __construct(
        private BrandingConfig $brandingConfig,
        private array $codebergOAuthConfig,
        private OAuthHttpClient $oAuthHttpClient,
        private OAuthScopeResolver $oAuthScopeResolver,
        private OAuthTokenResponseMapper $oAuthTokenResponseMapper,
    ) {
        $this->baseUrl = rtrim((string) ($this->codebergOAuthConfig['base_url'] ?? 'https://codeberg.org'), '/');
    }

    /**
     * @return list<string>
     */
    public function sourceScopes(): array
    {
        return $this->oAuthScopeResolver->sourceScopes($this->codebergOAuthConfig, self::DEFAULT_SOURCE_SCOPES);
    }

    public function getAuthorizationUrl(string $state, array $additionalScopes = []): string
    {
        return $this->buildAuthorizationUrl(
            $state,
            $this->oAuthScopeResolver->authorizationScopes($this->codebergOAuthConfig, self::DEFAULT_SCOPES, $additionalScopes),
            (string) ($this->codebergOAuthConfig['redirect_url'] ?? ''),
        );
    }

    /**
     * @param list<string> $additionalScopes
     */
    public function getAuthorizationUrlWithPkce(string $state, string $codeChallenge, array $additionalScopes = []): string
    {
        return $this->buildAuthorizationUrl(
            $state,
            $this->oAuthScopeResolver->authorizationScopes($this->codebergOAuthConfig, self::DEFAULT_SCOPES, $additionalScopes),
            (string) ($this->codebergOAuthConfig['redirect_url'] ?? ''),
            $codeChallenge,
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
            (string) ($this->codebergOAuthConfig['redirect_url'] ?? ''),
        );
    }

    /**
     * @param list<string> $scopes
     */
    public function getAuthorizationUrlForScopesWithPkce(string $state, array $scopes, string $codeChallenge): string
    {
        if ([] === $scopes) {
            return $this->getAuthorizationUrlWithPkce($state, $codeChallenge);
        }

        return $this->buildAuthorizationUrl(
            $state,
            $this->oAuthScopeResolver->unique($scopes),
            (string) ($this->codebergOAuthConfig['redirect_url'] ?? ''),
            $codeChallenge,
        );
    }

    public function exchangeCodeForToken(string $code, ?string $codeVerifier = null): OAuthTokenResponse
    {
        $payload = [
            'client_id'     => $this->codebergOAuthConfig['client_id'] ?? '',
            'client_secret' => $this->codebergOAuthConfig['client_secret'] ?? '',
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->codebergOAuthConfig['redirect_url'] ?? '',
        ];

        if (is_string($codeVerifier) && '' !== trim($codeVerifier)) {
            $payload['code_verifier'] = $codeVerifier;
        }

        $response = $this->postRequest(
            $this->resolveTokenUrl(),
            $payload,
        );

        return $this->tokenResponse($response, 'Failed to exchange code for Codeberg token');
    }

    public function fetchUser(string $accessToken): OAuthUserData
    {
        $response = $this->oAuthHttpClient->getJson(
            $this->resolveUserUrl(),
            [
                'Accept'        => 'application/json',
                'User-Agent'    => $this->brandingConfig->httpUserAgent(),
                'Authorization' => sprintf('Bearer %s', trim($accessToken)),
            ],
        );

        if (200 !== $response['status'] || ! is_array($response['body'])) {
            throw new RuntimeException('Failed to fetch user from Codeberg');
        }

        $userId = $response['body']['id'] ?? null;
        if (! is_int($userId) && ! is_string($userId)) {
            throw new RuntimeException('Codeberg user response missing id field');
        }

        return new OAuthUserData(
            providerUserId: (string) $userId,
            email: $this->fetchVerifiedEmail($accessToken),
            username: is_string($response['body']['login'] ?? null) ? $response['body']['login'] : null,
            displayName: is_string($response['body']['full_name'] ?? null) ? $response['body']['full_name'] : null,
            avatarUrl: is_string($response['body']['avatar_url'] ?? null) ? $response['body']['avatar_url'] : null,
        );
    }

    public function refreshToken(string $refreshToken): OAuthTokenResponse
    {
        $response = $this->postRequest(
            $this->resolveTokenUrl(),
            [
                'client_id'     => $this->codebergOAuthConfig['client_id'] ?? '',
                'client_secret' => $this->codebergOAuthConfig['client_secret'] ?? '',
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
            ],
        );

        return $this->tokenResponse($response, 'Failed to refresh Codeberg token', refresh: true);
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
    private function buildAuthorizationUrl(string $state, array $scopes, string $redirectUri, ?string $codeChallenge = null): string
    {
        $params = [
            'client_id'     => $this->codebergOAuthConfig['client_id'] ?? '',
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'state'         => $state,
        ];

        if (is_string($codeChallenge) && '' !== trim($codeChallenge)) {
            $params['code_challenge']        = $codeChallenge;
            $params['code_challenge_method'] = 'S256';
        }

        if ([] !== $scopes) {
            $params['scope'] = implode(' ', $scopes);
        }

        return sprintf('%s?%s', $this->resolveAuthorizationUrl(), http_build_query($params));
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
        return $this->nonEmptyConfigString('user_url') ?? $this->baseUrl . '/api/v1/user';
    }

    private function fetchVerifiedEmail(string $accessToken): ?string
    {
        $response = $this->oAuthHttpClient->getJson(
            $this->resolveUserUrl() . '/emails',
            [
                'Accept'        => 'application/json',
                'User-Agent'    => $this->brandingConfig->httpUserAgent(),
                'Authorization' => sprintf('Bearer %s', trim($accessToken)),
            ],
        );

        if (200 !== $response['status'] || ! is_array($response['body'])) {
            return null;
        }

        $verified = null;
        foreach ($response['body'] as $emailData) {
            if (! is_array($emailData) || ! is_string($emailData['email'] ?? null) || true !== ($emailData['verified'] ?? null)) {
                continue;
            }

            if (true === ($emailData['primary'] ?? null)) {
                return $emailData['email'];
            }

            $verified ??= $emailData['email'];
        }

        return $verified;
    }

    private function nonEmptyConfigString(string $key): ?string
    {
        $value = $this->codebergOAuthConfig[$key] ?? null;

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
                'User-Agent'   => $this->brandingConfig->httpUserAgent(),
            ],
        );
    }

    /**
     * @param array{status: int, body: null|array<int|string, mixed>} $response
     */
    private function tokenResponse(array $response, string $failureMessage, bool $refresh = false): OAuthTokenResponse
    {
        return $this->oAuthTokenResponseMapper->mapOAuthError(
            $response,
            $failureMessage . ': invalid response',
            'Codeberg OAuth error',
            $failureMessage,
            $refresh,
        );
    }
}
