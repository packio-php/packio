<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Git;

interface GitCommandRunnerInterface
{
    /**
     * @param array<string, string> $environment
     */
    public function run(string $command, array $environment = []): GitCommandResult;
}
