<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

use function is_string;

final readonly class UserListFilterDto
{
    public function __construct(public ?UserListStatus $status = null) {}

    /**
     * @param array<string, mixed> $params
     */
    public static function fromArray(array $params): self
    {
        $status = $params['status'] ?? null;

        return new self(is_string($status) ? UserListStatus::tryFrom($status) : null);
    }
}
