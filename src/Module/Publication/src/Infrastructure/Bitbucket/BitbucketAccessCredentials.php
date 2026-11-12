<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Bitbucket;

use function trim;

final readonly class BitbucketAccessCredentials
{
    public function __construct(public ?string $token = null) {}

    public function hasToken(): bool
    {
        return null !== $this->token && '' !== trim($this->token);
    }
}
