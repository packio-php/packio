<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

final readonly class UserSignInDto
{
    public function __construct(
        public string $id,
        public string $method,
        public string $methodLabel,
        public string $occurredAt,
        public ?string $ip,
        public ?string $userAgent,
    ) {}
}
