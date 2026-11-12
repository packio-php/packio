<?php

declare(strict_types=1);

namespace Packio\Worker\Api\Dto;

final readonly class JobLogDto
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $id,
        public string $jobId,
        public string $level,
        public string $message,
        public array $context,
        public string $createdAt,
    ) {}
}
