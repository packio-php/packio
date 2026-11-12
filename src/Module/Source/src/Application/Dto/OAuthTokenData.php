<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

final readonly class OAuthTokenData
{
    public function __construct(public string $accessToken, public ?string $refreshToken = null, public ?int $expiresIn = null) {}

    /**
     * @param array{access_token: string, refresh_token: ?string, expires_in: ?int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'] ?? null,
            expiresIn: $data['expires_in'] ?? null,
        );
    }
}
