<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

final readonly class IssuedApiAccessTokenDto
{
    public function __construct(
        public ApiAccessTokenDto $token,
        public string $plainTextToken,
    ) {}
}
