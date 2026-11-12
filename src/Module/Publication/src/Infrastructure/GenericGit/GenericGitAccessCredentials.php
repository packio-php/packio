<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\GenericGit;

use function trim;

final readonly class GenericGitAccessCredentials
{
    public function __construct(
        public string $cloneUrl,
        public ?string $token = null,
        public string $authScheme = 'bearer',
        public string $composerJsonPath = 'composer.json',
    ) {}

    public function hasToken(): bool
    {
        return null !== $this->token && '' !== trim($this->token);
    }
}
