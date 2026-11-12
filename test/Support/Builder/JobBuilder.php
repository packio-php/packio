<?php

declare(strict_types=1);

namespace PackioTest\Support\Builder;

use Packio\Worker\Api\Dto\JobDto;

final class JobBuilder extends BaseBuilder
{
    /** @param array<string, mixed> $overrides */
    public static function make(array $overrides = []): JobDto
    {
        return new JobDto(
            id: $overrides['id'] ?? self::uniqueToken('job'),
            organizationId: $overrides['organizationId'] ?? self::uniqueToken('org'),
            type: $overrides['type'] ?? 'test',
            payload: $overrides['payload'] ?? [],
            idempotencyKey: $overrides['idempotencyKey'] ?? null,
            status: $overrides['status'] ?? 'pending',
            priority: $overrides['priority'] ?? 0,
            scheduledAt: $overrides['scheduledAt'] ?? '',
            startedAt: $overrides['startedAt'] ?? null,
            completedAt: $overrides['completedAt'] ?? null,
            attemptCount: $overrides['attemptCount'] ?? 0,
            maxAttempts: $overrides['maxAttempts'] ?? 3,
            errorMessage: $overrides['errorMessage'] ?? null,
            errorDetails: $overrides['errorDetails'] ?? null,
            workerId: $overrides['workerId'] ?? null,
            createdAt: $overrides['createdAt'] ?? '',
            durationSeconds: $overrides['durationSeconds'] ?? null,
        );
    }
}
