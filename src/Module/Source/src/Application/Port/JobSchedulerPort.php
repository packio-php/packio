<?php

declare(strict_types=1);

namespace Packio\Source\Application\Port;

use Packio\Source\Application\Dto\ScheduledJob;

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
    ): ScheduledJob;

    /**
     * @param array<string, mixed> $payload
     *
     * @return string job id
     */
    public function enqueueOnceHeld(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0
    ): string;

    /**
     * @param array<string, mixed> $payload
     *
     * @return string job id
     */
    public function enqueueSourceSyncOnce(
        string $organizationId,
        string $sourceId,
        string $idempotencyKey,
        array $payload,
        int $priority = 0
    ): string;

    public function publishAfterAudit(string $jobId, string $organizationId): bool;

    public function confirmAudit(string $jobId, string $organizationId): bool;

    public function cancel(string $jobId, string $organizationId): bool;
}
