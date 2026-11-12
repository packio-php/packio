<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\GitlabSelfHosted;

use function rtrim;
use function trim;

final readonly class GitlabSelfHostedAccessCredentials
{
    public function __construct(public string $baseUrl, public ?string $token = null) {}

    public function hasToken(): bool
    {
        return null !== $this->token && '' !== trim($this->token);
    }

    public function apiBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/api/v4';
    }
}
