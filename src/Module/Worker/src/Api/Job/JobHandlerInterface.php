<?php

declare(strict_types=1);

namespace Packio\Worker\Api\Job;

interface JobHandlerInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload, JobLoggerInterface $jobLogger): JobResult;
}
