<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

final readonly class RegistrationResult
{
    public function __construct(public RegistrationStatus $status, public ?string $userId = null) {}
}
