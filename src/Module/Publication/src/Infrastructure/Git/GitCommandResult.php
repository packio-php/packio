<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Git;

final readonly class GitCommandResult
{
    public function __construct(
        public string $stdout,
        public string $stderr,
        public int $exitCode,
    ) {}

    public function successful(): bool
    {
        return 0 === $this->exitCode;
    }
}
