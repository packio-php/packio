<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

final readonly class UserModerationResultDto
{
    public function __construct(public UserModerationOutcome $outcome) {}

    public function isSuccess(): bool
    {
        return UserModerationOutcome::Success === $this->outcome;
    }
}
