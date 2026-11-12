<?php

declare(strict_types=1);

namespace Common\Infrastructure\Process;

final readonly class ProcessResult
{
    public function __construct(public string $stdout, public string $stderr, public int $exitCode) {}
}
