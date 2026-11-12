<?php

declare(strict_types=1);

namespace Packio\Worker\Application;

use Common\Logging\SafeExceptionContext;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;

class JobLogger implements JobLoggerInterface
{
    /** @var list<array{job_id: string, level: string, message: string, context: array<string, mixed>}> */
    private array $logs = [];

    public function __construct(private readonly string $jobId, private readonly JobLogRepositoryInterface $jobLogRepository) {}

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(JobLog::LEVEL_DEBUG, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(JobLog::LEVEL_INFO, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(JobLog::LEVEL_WARNING, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(JobLog::LEVEL_ERROR, $message, $context);
    }

    /**
     * @return list<array{job_id: string, level: string, message: string, context: array<string, mixed>}>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context): void
    {
        $message = SafeExceptionContext::redact($message);
        $context = SafeExceptionContext::redactContext($context);

        $this->logs[] = [
            'job_id'  => $this->jobId,
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];

        $this->jobLogRepository->save(
            JobLog::record(
                $this->jobId,
                $level,
                $message,
                $context,
            ),
        );
    }
}
