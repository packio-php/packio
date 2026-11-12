<?php

declare(strict_types=1);

namespace Packio\Worker\Api\Dto;

final readonly class JobDto
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $type,
        public array $payload,
        public ?string $idempotencyKey,
        public string $status,
        public int $priority,
        public string $scheduledAt,
        public ?string $startedAt,
        public ?string $completedAt,
        public int $attemptCount,
        public int $maxAttempts,
        public ?string $errorMessage,
        public ?string $errorDetails,
        public ?string $workerId,
        public string $createdAt,
        public ?int $durationSeconds,
        public int $warningCount = 0,
    ) {}

    public function canRetry(): bool
    {
        return $this->attemptCount < $this->maxAttempts;
    }
}
