<?php

declare(strict_types=1);

namespace PackioTest\Integration\Worker\Infrastructure\Facade;

use Packio\Worker\Api\JobApiFacadeInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class JobApiFacadeActiveSourceSyncTest extends IntegrationTestCase
{
    #[Test]
    public function reportsRunningThenPendingThenNullAndIgnoresOtherSources(): void
    {
        $orgId    = Uuid::uuid7()->toString();
        $sourceId = Uuid::uuid7()->toString();
        $otherId  = Uuid::uuid7()->toString();
        $facade   = $this->getService(JobApiFacadeInterface::class);

        self::assertNull($facade->activeSourceSyncStatus($orgId, $sourceId));

        $facade->enqueue($orgId, 'source.sync', [
            'source_id'       => $otherId,
            'organization_id' => $orgId,
        ]);
        self::assertNull($facade->activeSourceSyncStatus($orgId, $sourceId));
        self::assertSame('pending', $facade->activeSourceSyncStatus($orgId, $otherId));

        $pendingId = $facade->enqueue($orgId, 'source.sync', [
            'source_id'       => $sourceId,
            'organization_id' => $orgId,
        ])->id;
        self::assertSame('pending', $facade->activeSourceSyncStatus($orgId, $sourceId));

        $runningId = $facade->enqueue($orgId, 'source.sync', [
            'source_id'       => $sourceId,
            'organization_id' => $orgId,
        ])->id;
        $repo      = $this->getService(JobRepositoryInterface::class);
        $job       = $repo->findById($runningId);
        self::assertNotNull($job);
        $job->markRunning('worker-1');
        $repo->save($job);

        self::assertSame('running', $facade->activeSourceSyncStatus($orgId, $sourceId));
        self::assertNotSame($pendingId, $runningId);
    }
}
