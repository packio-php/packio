<?php

declare(strict_types=1);

namespace PackioTest\Integration\Worker\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class JobRepositoryCleanupTest extends IntegrationTestCase
{
    private const string ORGANIZATION_ID = 'cleanup-org';

    #[Test]
    public function itDeletesOnlyTerminalJobsOlderThanThreshold(): void
    {
        $deleted = [];
        foreach ([Job::STATUS_COMPLETED, Job::STATUS_FAILED, Job::STATUS_CANCELED] as $status) {
            $deleted[] = $this->createJob('test.job', [], $status, old: true);
        }

        $kept = [];
        foreach ([Job::STATUS_PENDING, Job::STATUS_HELD, Job::STATUS_AUDITED, Job::STATUS_RUNNING] as $status) {
            $kept[] = $this->createJob('test.job', [], $status, old: true);
        }

        $kept[] = $this->createJob('test.job', [], Job::STATUS_COMPLETED, old: false);

        self::assertSame(3, $this->jobRepository()->deleteOlderThan(new Chronos('-30 days')));
        self::assertSame($kept, $this->remainingJobIds([...$deleted, ...$kept]));
    }

    #[Test]
    public function itKeepsFailedSourceDeleteJobWhileSourceIsDeleting(): void
    {
        $deletingSourceId = $this->createSource('deleting');
        $activeSourceId   = $this->createSource('active');

        $protected = $this->createJob('source.delete', [
            'source_id' => $deletingSourceId,
        ], Job::STATUS_FAILED, old: true);
        $legacy    = $this->createJob('source.delete', [], Job::STATUS_FAILED, old: true, idempotencyKey: 'source-delete:' . $deletingSourceId);
        $removable = $this->createJob('source.delete', [
            'source_id' => $activeSourceId,
        ], Job::STATUS_FAILED, old: true);

        self::assertSame(1, $this->jobRepository()->deleteOlderThan(new Chronos('-30 days')));
        self::assertSame([$protected, $legacy], $this->remainingJobIds([$protected, $legacy, $removable]));
    }

    /** @param array<string, mixed> $payload */
    private function createJob(
        string $type,
        array $payload,
        string $status,
        bool $old,
        ?string $idempotencyKey = null
    ): string {
        $job = Job::schedule(self::ORGANIZATION_ID, $type, $payload, idempotencyKey: $idempotencyKey);
        $this->jobRepository()->save($job);
        $jobId = $job->getIdentifier()->toString();

        $this->database()->execute(
            'UPDATE jobs SET status = ?, created_at = ? WHERE uuid = ?',
            [$status, $old ? new Chronos('-40 days') : new Chronos(), $jobId],
        );

        return $jobId;
    }

    private function createSource(string $status): string
    {
        $sourceId = Uuid::uuid7()->toString();
        $this->database()->insert('sources')->values([
            'uuid'             => $sourceId,
            'organization_id'  => self::ORGANIZATION_ID,
            'provider'         => 'github',
            'canonical_url'    => 'https://github.com/acme/' . $sourceId,
            'display_name'     => 'acme/' . $sourceId,
            'provider_config'  => '{}',
            'default_ref'      => 'main',
            'visibility'       => 'private',
            'active'           => true,
            'last_sync_status' => 'never',
            'last_sync_error'  => '',
            'status'           => $status,
            'created_at'       => new Chronos(),
        ])->run();

        return $sourceId;
    }

    /**
     * @param list<string> $jobIds
     *
     * @return list<string>
     */
    private function remainingJobIds(array $jobIds): array
    {
        $remaining = [];
        foreach ($jobIds as $jobId) {
            if (false !== $this->database()->query('SELECT 1 FROM jobs WHERE uuid = ?', [$jobId])->fetchColumn()) {
                $remaining[] = $jobId;
            }
        }

        return $remaining;
    }

    private function jobRepository(): JobRepositoryInterface
    {
        $jobRepository = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobRepository);

        return $jobRepository;
    }

    private function database(): DatabaseInterface
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        return $databaseProvider->database();
    }
}
