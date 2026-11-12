<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

interface SourceDeletionExecutionLeaseInterface
{
    public function acquire(string $sourceId, string $organizationId, string $deletionRunId): bool;

    public function assertHeld(string $sourceId, string $organizationId, string $deletionRunId): void;

    public function commit(string $sourceId, string $organizationId, string $deletionRunId): void;

    public function rollback(string $sourceId, string $organizationId, string $deletionRunId): void;
}
