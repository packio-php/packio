<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Infrastructure\Adapter;

use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Application\Port\OrganizationDeletionJobSchedulerPort;
use Packio\Worker\Api\JobApiFacadeInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use Ramsey\Uuid\Uuid;

final class OrganizationDeletionJobSchedulerAdapterTest extends IntegrationTestCase
{
    public function testRestartReturnsActiveStageJobId(): void
    {
        [$organizationId, $runId, $activeId] = $this->stageJob();

        $port = $this->port();
        self::assertSame($activeId, $port->restartStage($organizationId, $runId, 'finalize', 'admin'));
        self::assertCount(1, $this->jobs()->findByOrganization($organizationId, [
            'type' => 'organization.delete',
        ]));
    }

    public function testRestartEnqueuesSuccessorOfTerminalJob(): void
    {
        [$organizationId, $runId, $failedId] = $this->stageJob(failed: true);

        $newId = $this->port()->restartStage($organizationId, $runId, 'finalize', 'admin');

        self::assertNotNull($newId);
        self::assertNotSame($failedId, $newId);
        $job = $this->jobs()->findById($newId);
        self::assertNotNull($job);
        self::assertSame('organization-delete-recovery:' . $organizationId . ':' . $runId . ':finalize:' . $failedId, $job->idempotencyKey());
        self::assertSame('admin', $job->payload()['requested_by_user_id'] ?? null);
    }

    public function testRestartIsIdempotentForTheSameTerminalJob(): void
    {
        [$organizationId, $runId] = $this->stageJob(failed: true);

        $port = $this->port();
        $a    = $port->restartStage($organizationId, $runId, 'finalize', 'admin');
        self::assertNotNull($a);
        self::assertSame($a, $port->restartStage($organizationId, $runId, 'finalize', 'admin'));
    }

    public function testRestartWithoutStageJobReturnsNull(): void
    {
        [$organizationId, $runId] = $this->stageJob();

        self::assertNull($this->port()->restartStage($organizationId, $runId, 'schedule_cleanup', 'admin'));
    }

    /** @return array{string, string, string} */
    private function stageJob(bool $failed = false): array
    {
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertInstanceOf(OrganizationApiFacadeInterface::class, $organizations);
        $organizationId = $organizations->registerOrganization('restart-' . Uuid::uuid7()->toString())->id;
        $runId          = Uuid::uuid7()->toString();

        $queue = $this->getService(JobApiFacadeInterface::class);
        self::assertInstanceOf(JobApiFacadeInterface::class, $queue);
        $jobId = $queue->enqueueOnce($organizationId, 'organization.delete', 'organization-delete:' . $organizationId . ':' . $runId . ':finalize', [
            'organization_id' => $organizationId,
            'deletion_run_id' => $runId,
            'stage'           => 'finalize',
        ])->id;

        if ($failed) {
            $job = $this->jobs()->findById($jobId);
            self::assertNotNull($job);
            $job->markRunning('worker');
            $job->markFailed('boom');
            $this->jobs()->save($job);
        }

        return [$organizationId, $runId, $jobId];
    }

    private function port(): OrganizationDeletionJobSchedulerPort
    {
        $port = $this->getService(OrganizationDeletionJobSchedulerPort::class);
        self::assertInstanceOf(OrganizationDeletionJobSchedulerPort::class, $port);

        return $port;
    }

    private function jobs(): JobRepositoryInterface
    {
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);

        return $jobs;
    }
}
