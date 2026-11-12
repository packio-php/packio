<?php

declare(strict_types=1);

namespace Packio\Worker\Application;

use Common\Logging\SafeExceptionContext;
use Packio\Worker\Api\Exception\WorkerRestartRequiredException;
use Packio\Worker\Api\Job\JobHandlerInterface;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Reporter\SentryReporter;
use Throwable;

use function array_key_exists;
use function is_scalar;

class JobProcessor
{
    private const array SAFE_PAYLOAD_CONTEXT_KEYS = [
        'organization_id',
        'source_id',
        'package_name',
        'version',
        'reference',
        'provider',
        'provider_instance_id',
        'provider_authorization_id',
        'source_oauth_account_id',
        'requested_by_user_id',
        'deletion_run_id',
        'reason',
        'idempotency_key',
    ];

    /** @var array<string, JobHandlerInterface> */
    private array $handlers = [];

    public function __construct(
        private readonly JobRepositoryInterface $jobRepository,
        private readonly JobLogRepositoryInterface $jobLogRepository,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?SentryLifecycle $sentryLifecycle = null,
        private readonly ?SentryReporter $sentryReporter = null,
    ) {}

    public function registerHandler(string $type, JobHandlerInterface $jobHandler): void
    {
        $this->handlers[$type] = $jobHandler;
    }

    public function process(Job $job): void
    {
        $handler = $this->handlers[$job->type()] ?? null;

        if (! $handler instanceof JobHandlerInterface) {
            $this->fail($job, "Unknown job type: {$job->type()}");

            return;
        }

        $jobLogger = new JobLogger($job->getIdentifier()->toString(), $this->jobLogRepository);

        $restartRequired = null;

        try {
            $jobLogger->info('Starting job processing', [
                'type'    => $job->type(),
                'attempt' => $job->attemptCount(),
            ]);

            $result = $handler->handle($job->payload(), $jobLogger);

            if ($result->isSuccess()) {
                $job->markCompleted();
                $jobLogger->info('Job completed successfully', [
                    'result' => $result->getData(),
                ]);
            } elseif (! $result->shouldRetry()) {
                $message = SafeExceptionContext::redact($result->getErrorMessage() ?? 'Job failed');
                $job->markFailed($message);
                $jobLogger->error('Job failed without retry', [
                    'result' => $result->getData(),
                ]);
                $this->reportPermanentFailure($job, $message);
            } else {
                throw new RuntimeException($result->getErrorMessage() ?? 'Job failed');
            }
        } catch (Throwable $e) {
            if ($e instanceof WorkerRestartRequiredException) {
                $restartRequired = $e;

                try {
                    $this->prepareRestartRequiredReconciliation($job, $e, $jobLogger);
                } catch (Throwable $secondaryFailure) {
                    $this->logSafely('critical', 'A secondary reconciliation operation failed; worker restart is still required.', [
                        'job_id'            => $job->getIdentifier()->toString(),
                        'type'              => $job->type(),
                        'secondary_failure' => $secondaryFailure,
                    ]);
                }
            } else {
                $this->handleFailure($job, $e, $jobLogger);
            }
        }

        try {
            $this->jobRepository->save($job);
        } catch (Throwable $persistenceFailure) {
            if ($restartRequired instanceof WorkerRestartRequiredException) {
                $this->logSafely('critical', 'Pending reconciliation state could not be persisted; worker restart is still required.', [
                    'job_id'                => $job->getIdentifier()->toString(),
                    'type'                  => $job->type(),
                    'persistence_exception' => $persistenceFailure,
                ]);

                throw $restartRequired;
            }

            throw $persistenceFailure;
        }

        if ($restartRequired instanceof WorkerRestartRequiredException) {
            throw $restartRequired;
        }
    }

    private function handleFailure(Job $job, Throwable $throwable, JobLogger $jobLogger): void
    {
        $jobLogger->error('Job failed: ' . $throwable->getMessage());

        if ($job->canRetry()) {
            $job->markPendingForRetry();
            $jobLogger->info('Job scheduled for retry', [
                'next_attempt' => $job->attemptCount() + 1,
                'max_attempts' => $job->maxAttempts(),
            ]);
        } else {
            $job->markFailed(SafeExceptionContext::redact($throwable->getMessage()));
            if ($this->logger instanceof LoggerInterface) {
                $this->logger->error('Job permanently failed', [
                    'job_id'    => $job->getIdentifier()->toString(),
                    'type'      => $job->type(),
                    'attempts'  => $job->attemptCount(),
                    'exception' => $throwable,
                ]);
            }
            $this->reportPermanentFailure($job, $throwable->getMessage(), $throwable);
        }
    }

    private function prepareRestartRequiredReconciliation(
        Job $job,
        WorkerRestartRequiredException $workerRestartRequiredException,
        JobLogger $jobLogger,
    ): void {
        $job->markPendingForInfrastructureRetry();

        try {
            $jobLogger->error('Job failed: ' . $workerRestartRequiredException->getMessage());
            $jobLogger->warning('Job remains pending because infrastructure outcome requires reconciliation.', [
                'attempt'      => $job->attemptCount(),
                'max_attempts' => $job->maxAttempts(),
            ]);
        } catch (Throwable $loggingFailure) {
            $this->logSafely('error', 'Job-log persistence failed after the pending reconciliation state was prepared.', [
                'job_id'          => $job->getIdentifier()->toString(),
                'type'            => $job->type(),
                'logging_failure' => $loggingFailure,
            ]);
        }

        $this->logSafely('warning', 'Restart-required job was preserved for reconciliation.', [
            'job_id'       => $job->getIdentifier()->toString(),
            'type'         => $job->type(),
            'attempt'      => $job->attemptCount(),
            'max_attempts' => $job->maxAttempts(),
        ]);
    }

    /** @param array<string, mixed> $context */
    private function logSafely(string $level, string $message, array $context): void
    {
        try {
            $this->logger?->log($level, $message, $context);
        } catch (Throwable) {
            // Secondary diagnostics must never replace a fail-closed worker restart.
        }
    }

    private function fail(Job $job, string $message): void
    {
        $job->markFailed($message);
        $this->reportPermanentFailure($job, $message);

        $this->jobLogRepository->save(
            JobLog::record($job->getIdentifier()->toString(), JobLog::LEVEL_ERROR, $message),
        );

        $this->jobRepository->save($job);
    }

    private function reportPermanentFailure(Job $job, string $message, ?Throwable $throwable = null): void
    {
        if (! $this->sentryLifecycle instanceof SentryLifecycle || ! $this->sentryReporter instanceof SentryReporter) {
            return;
        }

        try {
            $this->sentryLifecycle->withIsolatedScope(function() use ($job, $message, $throwable): void {
                $jobId = $job->getIdentifier()->toString();

                $this->sentryReporter->setTag('component', 'worker');
                $this->sentryReporter->setTag('job_type', '' !== $job->type() ? $job->type() : 'unknown');
                $this->sentryReporter->setTag('job_id', $jobId);
                $this->sentryReporter->setContext('job', [
                    'id'              => $jobId,
                    'type'            => $job->type(),
                    'organization_id' => $job->organizationId(),
                    'status'          => $job->status(),
                    'attempt'         => $job->attemptCount(),
                    'max_attempts'    => $job->maxAttempts(),
                    'worker_id'       => $job->workerId(),
                    'idempotency_key' => $job->idempotencyKey(),
                    'payload'         => $this->safePayloadContext($job->payload()),
                ]);

                if ($throwable instanceof Throwable) {
                    $this->sentryReporter->captureException($throwable);

                    return;
                }

                $this->sentryReporter->captureMessage('Worker job permanently failed', 'error', [
                    'message' => $message,
                ]);
            });
            $this->sentryLifecycle->flush();
        } catch (Throwable $reportingFailure) {
            $this->logger?->warning('Failed to report worker job failure to Sentry', [
                'exception' => $reportingFailure,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, null|scalar>
     */
    private function safePayloadContext(array $payload): array
    {
        $context = [];
        foreach (self::SAFE_PAYLOAD_CONTEXT_KEYS as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (null === $value || is_scalar($value)) {
                $context[$key] = $value;
            }
        }

        return $context;
    }
}
