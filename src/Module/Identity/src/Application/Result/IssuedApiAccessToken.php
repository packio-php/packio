<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

final readonly class IssuedApiAccessToken
{
    public function __construct(
        public ApiAccessTokenResult $token,
        public string $plainTextToken,
    ) {}
}
