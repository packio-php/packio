<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

/**
 * Result of an atomic deletion-initiation attempt, exposed across the module
 * boundary. `jobId` is set only for `initiated` and identifies the queued
 * cleanup job so a caller can compensate the whole lifecycle on later
 * failures (e.g. a failed audit write).
 */
final readonly class SourceDeletionInitiationResult
{
    public function __construct(
        public SourceDeletionInitiationOutcome $outcome,
        public ?string $jobId = null,
        public bool $previousActive = true,
        public ?string $deletionRunId = null,
    ) {}
}
