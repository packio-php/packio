<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Github;

use function trim;

final readonly class GithubAccessCredentials
{
    public function __construct(public ?string $token = null) {}

    public function hasToken(): bool
    {
        return null !== $this->token && '' !== trim($this->token);
    }
}
