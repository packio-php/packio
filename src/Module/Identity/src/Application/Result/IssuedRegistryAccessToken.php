<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

final readonly class IssuedRegistryAccessToken
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $plainTextToken,
        public string $maskedToken,
        public array $permissions,
        public string $createdAt,
    ) {}
}
