<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Adapter;

use Packio\Package\Application\Port\JobSchedulerPort;
use Packio\Worker\Api\JobApiFacadeInterface;

final readonly class WorkerJobSchedulerAdapter implements JobSchedulerPort
{
    public function __construct(private JobApiFacadeInterface $jobApiFacade) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(string $organizationId, string $type, array $payload, int $priority = 0): void
    {
        $this->jobApiFacade->enqueue($organizationId, $type, $payload, $priority);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueueOnce(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0
    ): void {
        $this->jobApiFacade->enqueueOnce($organizationId, $type, $idempotencyKey, $payload, $priority);
    }
}
