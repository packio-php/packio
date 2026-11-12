<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth\Client;

use Common\Branding\BrandingConfig;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;
use Packio\Identity\Infrastructure\OAuth\OAuthClientInterface;
use RuntimeException;

use function base64_encode;
use function http_build_query;
use function implode;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;
use function trim;

final readonly class BitbucketOAuthClient implements OAuthClientInterface
{
    private const array DEFAULT_SCOPES        = ['account', 'email'];
    private const array DEFAULT_SOURCE_SCOPES = ['account', 'email', 'repository', 'webhook'];

    /**
     * @param array<string, mixed> $bitbucketOAuthConfig
     */
    public function __construct(
        private BrandingConfig $brandingConfig,
        private array $bitbucketOAuthConfig,
        private OAuthHttpClient $oAuthHttpClient,
        private OAuthScopeResolver $oAuthScopeResolver,
        private OAuthTokenResponseMapper $oAuthTokenResponseMapper,
    ) {}

    /**
     * @return list<string>
     */
    public function sourceScopes(): array
    {
        return $this->oAuthScopeResolver->sourceScopes($this->bitbucketOAuthConfig, self::DEFAULT_SOURCE_SCOPES);
    }

    public function getAuthorizationUrl(string $state, array $additionalScopes = []): string
    {
        return $this->buildAuthorizationUrl(
            $state,
            $this->oAuthScopeResolver->authorizationScopes($this->bitbucketOAuthConfig, self::DEFAULT_SCOPES, $additionalScopes),
            (string) ($this->bitbucketOAuthConfig['redirect_url'] ?? ''),
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
            (string) ($this->bitbucketOAuthConfig['redirect_url'] ?? ''),
        );
    }

    public function exchangeCodeForToken(string $code, ?string $codeVerifier = null): OAuthTokenResponse
    {
        unset($codeVerifier);

        $response = $this->postTokenRequest([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $this->bitbucketOAuthConfig['redirect_url'] ?? '',
        ]);

        return $this->tokenResponse($response, 'Failed to exchange code for Bitbucket token');
    }

    public function fetchUser(string $accessToken): OAuthUserData
    {
        $response = $this->oAuthHttpClient->getJson(
            $this->nonEmptyConfigString('user_url') ?? 'https://api.bitbucket.org/2.0/user',
            [
                'Accept'        => 'application/json',
                'User-Agent'    => $this->brandingConfig->httpUserAgent(),
                'Authorization' => 'Bearer ' . trim($accessToken),
            ],
        );

        if (200 !== $response['status'] || ! is_array($response['body'])) {
            throw new RuntimeException('Failed to fetch user from Bitbucket');
        }

        $userId = $response['body']['account_id'] ?? $response['body']['uuid'] ?? null;
        if (! is_string($userId) || '' === trim($userId)) {
            throw new RuntimeException('Bitbucket user response missing account id field');
        }

        return new OAuthUserData(
            providerUserId: trim($userId, '{}'),
            email: $this->fetchPrimaryEmail($accessToken),
            username: is_string($response['body']['nickname'] ?? null) ? $response['body']['nickname'] : null,
            displayName: is_string($response['body']['display_name'] ?? null) ? $response['body']['display_name'] : null,
            avatarUrl: is_string($response['body']['links']['avatar']['href'] ?? null) ? $response['body']['links']['avatar']['href'] : null,
        );
    }

    public function refreshToken(string $refreshToken): OAuthTokenResponse
    {
        $response = $this->postTokenRequest([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        return $this->tokenResponse($response, 'Failed to refresh Bitbucket token', refresh: true);
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
        return sprintf(
            '%s?%s',
            $this->nonEmptyConfigString('authorization_url') ?? 'https://bitbucket.org/site/oauth2/authorize',
            http_build_query([
                'client_id'     => $this->bitbucketOAuthConfig['client_id'] ?? '',
                'redirect_uri'  => $redirectUri,
                'response_type' => 'code',
                'scope'         => implode(' ', $scopes),
                'state'         => $state,
            ]),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{status: int, body: null|array<int|string, mixed>}
     */
    private function postTokenRequest(array $payload): array
    {
        $clientId     = (string) ($this->bitbucketOAuthConfig['client_id'] ?? '');
        $clientSecret = (string) ($this->bitbucketOAuthConfig['client_secret'] ?? '');

        return $this->oAuthHttpClient->postForm(
            $this->nonEmptyConfigString('token_url') ?? 'https://bitbucket.org/site/oauth2/access_token',
            $payload,
            [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
            ],
        );
    }

    private function fetchPrimaryEmail(string $accessToken): ?string
    {
        $response = $this->oAuthHttpClient->getJson(
            $this->nonEmptyConfigString('emails_url') ?? 'https://api.bitbucket.org/2.0/user/emails',
            [
                'Accept'        => 'application/json',
                'User-Agent'    => $this->brandingConfig->httpUserAgent(),
                'Authorization' => 'Bearer ' . trim($accessToken),
            ],
        );

        if (200 !== $response['status'] || ! is_array($response['body'])) {
            return null;
        }

        foreach ((array) ($response['body']['values'] ?? []) as $emailData) {
            if (! is_array($emailData)) {
                continue;
            }

            $email       = $emailData['email'] ?? null;
            $isPrimary   = $emailData['is_primary'] ?? false;
            $isConfirmed = $emailData['is_confirmed'] ?? false;

            if (is_string($email) && is_bool($isPrimary) && $isPrimary && true === $isConfirmed) {
                return $email;
            }
        }

        return null;
    }

    /**
     * @param array{status: int, body: null|array<int|string, mixed>} $response
     */
    private function tokenResponse(array $response, string $message, bool $refresh = false): OAuthTokenResponse
    {
        return $this->oAuthTokenResponseMapper->mapOAuthError(
            $response,
            $message . ': invalid response',
            'Bitbucket OAuth error',
            $message,
            $refresh,
        );
    }

    private function nonEmptyConfigString(string $key): ?string
    {
        $value = $this->bitbucketOAuthConfig[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
