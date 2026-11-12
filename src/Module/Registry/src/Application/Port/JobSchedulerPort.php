<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Port;

interface JobSchedulerPort
{
    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(string $organizationId, string $type, array $payload): void;
}
