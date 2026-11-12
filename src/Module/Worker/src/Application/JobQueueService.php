<?php

declare(strict_types=1);

namespace Packio\Worker\Application;

use Cake\Chronos\Chronos;
use InvalidArgumentException;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;

use function trim;
use function usleep;

class JobQueueService
{
    public function __construct(private readonly JobRepositoryInterface $jobRepository) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(
        string $organizationId,
        string $type,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): Job {
        $job = Job::schedule(
            organizationId: $organizationId,
            type: $type,
            payload: $payload,
            priority: $priority,
            scheduledAt: $scheduledAt,
            maxAttempts: $maxAttempts,
        );

        $this->jobRepository->save($job);

        return $job;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueueOnce(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): Job {
        return $this->enqueueOnceWithResult($organizationId, $type, $idempotencyKey, $payload, $priority, $scheduledAt, $maxAttempts)->job;
    }

    /** @param array<string, mixed> $payload */
    public function enqueueSourceCreation(string $organizationId, string $idempotencyKey, array $payload): EnqueueResult
    {
        $resolvedKey = trim($idempotencyKey);
        if ('' !== $resolvedKey) {
            $this->jobRepository->releaseTerminalIdempotencyKey($organizationId, 'source.create', $resolvedKey);
        }

        return $this->enqueueOnceWithResult($organizationId, 'source.create', $resolvedKey, $payload);
    }

    /** @param array<string, mixed> $payload */
    public function enqueueOnceWithResult(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): EnqueueResult {
        $resolvedKey = trim($idempotencyKey);
        if ('' === $resolvedKey) {
            return new EnqueueResult($this->enqueue($organizationId, $type, $payload, $priority, $scheduledAt, $maxAttempts), true);
        }

        $existing = $this->jobRepository->findByIdempotencyKey($organizationId, $type, $resolvedKey);
        if ($existing instanceof Job) {
            return new EnqueueResult($existing, false);
        }

        $job = Job::schedule(
            organizationId: $organizationId,
            type: $type,
            payload: $payload,
            priority: $priority,
            scheduledAt: $scheduledAt,
            maxAttempts: $maxAttempts,
            idempotencyKey: $resolvedKey,
        );

        try {
            $this->jobRepository->save($job);
        } catch (RepositoryPersistException $exception) {
            $existing = $this->findConcurrentJob($organizationId, $type, $resolvedKey);
            if ($existing instanceof Job) {
                return new EnqueueResult($existing, false);
            }

            throw $exception;
        }

        return new EnqueueResult($job, true);
    }

    /**
     * Creates a job in a non-executable held state. A caller must publish the
     * exact job after its surrounding lifecycle work (such as an audit write)
     * has completed successfully.
     *
     * @param array<string, mixed> $payload
     */
    public function enqueueOnceHeld(
        string $organizationId,
        string $type,
        string $idempotencyKey,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): Job {
        $resolvedKey = trim($idempotencyKey);
        if ('' === $resolvedKey) {
            throw new InvalidArgumentException('Held jobs require an idempotency key.');
        }

        $existing = $this->jobRepository->findByIdempotencyKey($organizationId, $type, $resolvedKey);
        if ($existing instanceof Job) {
            return $existing;
        }

        $job = Job::hold(
            organizationId: $organizationId,
            type: $type,
            payload: $payload,
            priority: $priority,
            scheduledAt: $scheduledAt,
            maxAttempts: $maxAttempts,
            idempotencyKey: $resolvedKey,
        );

        try {
            $this->jobRepository->save($job);
        } catch (RepositoryPersistException $exception) {
            $existing = $this->findConcurrentJob($organizationId, $type, $resolvedKey);
            if ($existing instanceof Job) {
                return $existing;
            }

            throw $exception;
        }

        return $job;
    }

    public function publish(string $jobId, string $organizationId): bool
    {
        $job = $this->jobRepository->findById($jobId);
        if (! $job instanceof Job || $job->organizationId() !== $organizationId || ! $job->isAudited()) {
            return $job instanceof Job && $job->organizationId() === $organizationId && ($job->isPending() || $job->isRunning());
        }

        if ($this->jobRepository->advanceLifecycle(
            $jobId,
            $organizationId,
            Job::STATUS_AUDITED,
            Job::STATUS_PENDING,
        )) {
            return true;
        }

        $currentJob = $this->jobRepository->findById($jobId);

        return $currentJob instanceof Job
            && $currentJob->organizationId() === $organizationId
            && ($currentJob->isPending() || $currentJob->isRunning());
    }

    public function confirmAudit(string $jobId, string $organizationId): bool
    {
        $job = $this->jobRepository->findById($jobId);
        if (! $job instanceof Job || $job->organizationId() !== $organizationId || ! $job->isHeld()) {
            return $job instanceof Job && $job->organizationId() === $organizationId && ($job->isAudited() || $job->isPending() || $job->isRunning());
        }

        if ($this->jobRepository->advanceLifecycle(
            $jobId,
            $organizationId,
            Job::STATUS_HELD,
            Job::STATUS_AUDITED,
        )) {
            return true;
        }

        $currentJob = $this->jobRepository->findById($jobId);

        return $currentJob instanceof Job
            && $currentJob->organizationId() === $organizationId
            && ($currentJob->isAudited() || $currentJob->isPending() || $currentJob->isRunning());
    }

    /**
     * Advances an exact lifecycle job after its audit operation is durable.
     * The operation is intentionally resumable across process crashes.
     */
    public function publishAfterAudit(string $jobId, string $organizationId): bool
    {
        $job = $this->jobRepository->findById($jobId);
        if (! $job instanceof Job || $job->organizationId() !== $organizationId) {
            return false;
        }

        if ($job->isHeld()) {
            if (! $this->confirmAudit($jobId, $organizationId)) {
                return false;
            }

            $job = $this->jobRepository->findById($jobId);
            if (! $job instanceof Job || $job->organizationId() !== $organizationId) {
                return false;
            }
        }

        if ($job->isAudited() && ! $this->publish($jobId, $organizationId)) {
            return false;
        }

        $currentJob = $this->jobRepository->findById($jobId);

        return $currentJob instanceof Job
            && $currentJob->organizationId() === $organizationId
            && ($currentJob->isPending() || $currentJob->isRunning());
    }

    /**
     * Publishes the exact lifecycle job only when its audit phase has already
     * completed. This makes retrying a producer after a crash between audit
     * recording and publication safe and idempotent.
     */
    public function publishAuditedByIdempotencyKey(string $organizationId, string $type, string $idempotencyKey): bool
    {
        $job = $this->jobRepository->findByIdempotencyKey($organizationId, $type, trim($idempotencyKey));
        if (! $job instanceof Job || ! $job->isAudited()) {
            return false;
        }

        return $this->publish($job->getIdentifier()->toString(), $organizationId);
    }

    /**
     * Coalesce webhook-triggered source.sync jobs by reusing a pending one for the same source.
     *
     * Unlike {@see enqueueOnce}, this never reuses a completed/running job, so a webhook that
     * arrives after a previous sync already finished (e.g. a tag created seconds after a push
     * webhook was synced) still schedules a fresh sync instead of being suppressed.
     *
     * @param array<string, mixed> $payload
     */
    public function enqueueSourceSyncOnce(
        string $organizationId,
        string $sourceId,
        string $idempotencyKey,
        array $payload,
        int $priority = 0,
        ?Chronos $scheduledAt = null,
        int $maxAttempts = 3,
    ): Job {
        $pending = $this->jobRepository->findByOrganization($organizationId, [
            'status'    => Job::STATUS_PENDING,
            'type'      => 'source.sync',
            'source_id' => $sourceId,
            'limit'     => 1,
        ]);

        if ([] !== $pending) {
            return $pending[0];
        }

        return $this->enqueueOnce(
            $organizationId,
            'source.sync',
            $idempotencyKey,
            $payload,
            $priority,
            $scheduledAt,
            $maxAttempts,
        );
    }

    public function cancel(string $jobId): bool
    {
        $job = $this->jobRepository->findById($jobId);

        if (! $job instanceof Job || ! $job->isCancelable()) {
            return false;
        }

        $job->markCanceled();
        $this->jobRepository->save($job);

        return true;
    }

    private function findConcurrentJob(string $organizationId, string $type, string $idempotencyKey): ?Job
    {
        // PostgreSQL may report a unique-index lock before the winner's
        // transaction is visible to our follow-up read. Retry only that
        // expected typed persistence outcome; all other failures propagate.
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $existing = $this->jobRepository->findByIdempotencyKey($organizationId, $type, $idempotencyKey);
            if ($existing instanceof Job) {
                return $existing;
            }

            usleep(10_000);
        }

        return null;
    }
}
