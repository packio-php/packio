<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

final readonly class SignInResult
{
    public function __construct(public SignInStatus $status, public ?string $userId = null) {}
}
