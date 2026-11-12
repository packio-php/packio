<?php

declare(strict_types=1);

namespace Packio\Package\Application\Port;

interface JobSchedulerPort
{
    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(string $organizationId, string $type, array $payload, int $priority = 0): void;

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueueOnce(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0
    ): void;
}
