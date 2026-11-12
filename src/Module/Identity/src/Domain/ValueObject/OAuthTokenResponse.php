<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\ValueObject;

final readonly class OAuthTokenResponse
{
    public function __construct(public string $accessToken, public ?string $refreshToken, public ?int $expiresIn) {}
}
