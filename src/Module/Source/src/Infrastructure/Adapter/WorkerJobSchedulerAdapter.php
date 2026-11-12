<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Adapter;

use Packio\Source\Application\Dto\ScheduledJob;
use Packio\Source\Application\Port\JobSchedulerPort;
use Packio\Worker\Api\JobApiFacadeInterface;

use function in_array;

final readonly class WorkerJobSchedulerAdapter implements JobSchedulerPort
{
    private const array TERMINAL_STATUSES = ['canceled', 'completed', 'failed'];

    public function __construct(private JobApiFacadeInterface $jobApiFacade) {}

    public function enqueue(string $organizationId, string $type, array $payload, int $priority = 0): void
    {
        $this->jobApiFacade->enqueue($organizationId, $type, $payload, $priority);
    }

    public function enqueueOnce(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0
    ): ScheduledJob {
        $jobDto = $this->jobApiFacade->enqueueOnce($organizationId, $type, $idempotencyKey, $payload, $priority);

        return new ScheduledJob($jobDto->id, in_array($jobDto->status, self::TERMINAL_STATUSES, true));
    }

    public function enqueueOnceHeld(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0
    ): string {
        return $this->jobApiFacade->enqueueOnceHeld($organizationId, $type, $idempotencyKey, $payload, $priority)->id;
    }

    public function enqueueSourceSyncOnce(
        string $organizationId,
        string $sourceId,
        string $idempotencyKey,
        array $payload,
        int $priority = 0
    ): string {
        return $this->jobApiFacade->enqueueSourceSyncOnce($organizationId, $sourceId, $idempotencyKey, $payload, $priority)->id;
    }

    public function publishAfterAudit(string $jobId, string $organizationId): bool
    {
        return $this->jobApiFacade->publishAfterAudit($jobId, $organizationId);
    }

    public function confirmAudit(string $jobId, string $organizationId): bool
    {
        return $this->jobApiFacade->confirmAudit($jobId, $organizationId);
    }

    public function cancel(string $jobId, string $organizationId): bool
    {
        return $this->jobApiFacade->cancel($jobId, $organizationId);
    }
}
