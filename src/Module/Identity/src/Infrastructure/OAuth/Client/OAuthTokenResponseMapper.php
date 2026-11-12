<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth\Client;

use Packio\Identity\Api\Exception\OAuthRefreshRejectedException;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use RuntimeException;

use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

final readonly class OAuthTokenResponseMapper
{
    /**
     * @param array{status: int, body: null|array<int|string, mixed>} $response
     */
    public function mapOAuthError(
        array $response,
        string $invalidResponseMessage,
        string $errorPrefix,
        string $defaultErrorDescription,
        bool $refresh = false,
    ): OAuthTokenResponse {
        $body = $this->bodyOrFail($response, $invalidResponseMessage, $refresh);

        $accessToken = $body['access_token'] ?? null;
        if (! is_string($accessToken)) {
            $error            = $body['error'] ?? 'unknown_error';
            $errorDescription = $body['error_description'] ?? $defaultErrorDescription;

            $message = sprintf('%s (%s): %s', $errorPrefix, $error, $errorDescription);

            throw $refresh ? $this->refreshFailure($response, $message) : new RuntimeException($message);
        }

        return $this->tokenResponse($accessToken, $body);
    }

    /**
     * Maps a refresh-token response; only used by refresh calls.
     *
     * @param array{status: int, body: null|array<int|string, mixed>} $response
     */
    public function mapRequiredAccessToken(
        array $response,
        string $invalidResponseMessage,
        string $missingAccessTokenMessage,
    ): OAuthTokenResponse {
        $body = $this->bodyOrFail($response, $invalidResponseMessage, true);

        $accessToken = $body['access_token'] ?? null;
        if (! is_string($accessToken)) {
            throw $this->refreshFailure($response, $missingAccessTokenMessage);
        }

        return $this->tokenResponse($accessToken, $body);
    }

    /**
     * @param array{status: int, body: null|array<int|string, mixed>} $response
     *
     * @return array<int|string, mixed>
     */
    private function bodyOrFail(array $response, string $invalidResponseMessage, bool $refresh): array
    {
        if (! is_array($response['body'])) {
            throw $refresh ? $this->refreshFailure($response, $invalidResponseMessage) : new RuntimeException($invalidResponseMessage);
        }

        return $response['body'];
    }

    /**
     * A failed refresh is transient only for no response, 5xx, 429 or a temporary OAuth error code;
     * every other failure (including GitHub's bad_refresh_token with status 200) is a rejection.
     *
     * @param array{status: int, body: null|array<int|string, mixed>} $response
     */
    private function refreshFailure(array $response, string $message): RuntimeException
    {
        $status = $response['status'];
        $error  = is_array($response['body']) ? ($response['body']['error'] ?? null) : null;

        $transient = 0 === $status
            || 429 === $status
            || $status >= 500
            || in_array($error, ['temporarily_unavailable', 'server_error'], true);

        return $transient ? new RuntimeException($message) : new OAuthRefreshRejectedException($message);
    }

    /**
     * @param array<int|string, mixed> $body
     */
    private function tokenResponse(string $accessToken, array $body): OAuthTokenResponse
    {
        return new OAuthTokenResponse(
            accessToken: $accessToken,
            refreshToken: is_string($body['refresh_token'] ?? null)
                ? $body['refresh_token']
                : null,
            expiresIn: is_int($body['expires_in'] ?? null)
                ? $body['expires_in']
                : null,
        );
    }
}
