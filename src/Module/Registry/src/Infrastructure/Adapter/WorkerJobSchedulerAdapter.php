<?php

declare(strict_types=1);

namespace Packio\Registry\Infrastructure\Adapter;

use Packio\Registry\Application\Port\JobSchedulerPort;
use Packio\Worker\Api\JobApiFacadeInterface;

final readonly class WorkerJobSchedulerAdapter implements JobSchedulerPort
{
    public function __construct(private JobApiFacadeInterface $jobApiFacade) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(string $organizationId, string $type, array $payload): void
    {
        $this->jobApiFacade->enqueue($organizationId, $type, $payload);
    }
}
