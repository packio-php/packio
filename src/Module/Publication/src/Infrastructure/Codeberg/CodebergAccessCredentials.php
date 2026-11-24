<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Codeberg;

use function rtrim;
use function trim;

final readonly class CodebergAccessCredentials
{
    public string $baseUrl;

    public function __construct(public ?string $token = null, string $baseUrl = 'https://codeberg.org')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function hasToken(): bool
    {
        return null !== $this->token && '' !== trim($this->token);
    }
}
