<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Dto;

use Packio\Identity\Application\Command\OAuth\ResolveOAuthCallbackCommand;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;

use function is_int;
use function is_string;
use function time;

final readonly class PendingOAuthEmailContext
{
    public function __construct(
        public string $provider,
        public string $accessToken,
        public ?string $refreshToken,
        public ?int $expiresIn,
        public string $providerUserId,
        public ?string $providerUsername,
        public ?string $providerDisplayName,
        public ?string $providerAvatarUrl,
        public string $registrationRedirectTarget,
        public int $createdAt,
    ) {}

    public static function fromOAuth(
        OAuthProvider $oAuthProvider,
        OAuthTokenResponse $oAuthTokenResponse,
        OAuthUserData $oAuthUserData,
        string $registrationRedirectTarget,
    ): self {
        return new self(
            provider: $oAuthProvider->value,
            accessToken: $oAuthTokenResponse->accessToken,
            refreshToken: $oAuthTokenResponse->refreshToken,
            expiresIn: $oAuthTokenResponse->expiresIn,
            providerUserId: $oAuthUserData->providerUserId,
            providerUsername: $oAuthUserData->username,
            providerDisplayName: $oAuthUserData->displayName,
            providerAvatarUrl: $oAuthUserData->avatarUrl,
            registrationRedirectTarget: $registrationRedirectTarget,
            createdAt: time(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        if (
            ! is_string($data['provider'] ?? null)
            || ! is_string($data['access_token'] ?? null)
            || ! is_string($data['provider_user_id'] ?? null)
            || ! is_int($data['created_at'] ?? null)
        ) {
            return null;
        }

        return new self(
            provider: $data['provider'],
            accessToken: $data['access_token'],
            refreshToken: is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
            expiresIn: is_int($data['expires_in'] ?? null) ? $data['expires_in'] : null,
            providerUserId: $data['provider_user_id'],
            providerUsername: is_string($data['provider_username'] ?? null) ? $data['provider_username'] : null,
            providerDisplayName: is_string($data['provider_display_name'] ?? null) ? $data['provider_display_name'] : null,
            providerAvatarUrl: is_string($data['provider_avatar_url'] ?? null) ? $data['provider_avatar_url'] : null,
            registrationRedirectTarget: is_string($data['registration_redirect_target'] ?? null) ? $data['registration_redirect_target'] : '',
            createdAt: $data['created_at'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider'                     => $this->provider,
            'access_token'                 => $this->accessToken,
            'refresh_token'                => $this->refreshToken,
            'expires_in'                   => $this->expiresIn,
            'provider_user_id'             => $this->providerUserId,
            'provider_username'            => $this->providerUsername,
            'provider_display_name'        => $this->providerDisplayName,
            'provider_avatar_url'          => $this->providerAvatarUrl,
            'registration_redirect_target' => $this->registrationRedirectTarget,
            'created_at'                   => $this->createdAt,
        ];
    }

    public function command(?string $email = null): ?ResolveOAuthCallbackCommand
    {
        $provider = OAuthProvider::tryFrom($this->provider);
        if (null === $provider || '' === $this->accessToken || '' === $this->providerUserId) {
            return null;
        }

        return new ResolveOAuthCallbackCommand($provider, $this->tokenResponse(), $this->userDataWithEmail($email ?? ''), registrationRedirectTarget: $this->registrationRedirectTarget, email: $email);
    }

    public function tokenResponse(): OAuthTokenResponse
    {
        return new OAuthTokenResponse($this->accessToken, $this->refreshToken, $this->expiresIn);
    }

    public function userDataWithEmail(string $email): OAuthUserData
    {
        return new OAuthUserData(
            providerUserId: $this->providerUserId,
            email: $email,
            username: $this->providerUsername,
            displayName: $this->providerDisplayName,
            avatarUrl: $this->providerAvatarUrl,
        );
    }
}
