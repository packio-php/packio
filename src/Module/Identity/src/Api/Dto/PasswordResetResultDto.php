<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

final readonly class PasswordResetResultDto
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public bool $success,
        public ?string $reason = null,
        public ?string $userUuid = null,
        public array $errors = []
    ) {}
}
