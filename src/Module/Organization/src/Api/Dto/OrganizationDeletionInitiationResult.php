<?php

declare(strict_types=1);

namespace Packio\Organization\Api\Dto;

/**
 * Lifecycle initiation outcome. Recovery identifiers may also be present on
 * repeated attempts and publication failures; only a new initiation may be compensated.
 */
final readonly class OrganizationDeletionInitiationResult
{
    public function __construct(
        public OrganizationDeletionInitiationOutcome $outcome,
        public ?string $jobId = null,
        public bool $previousActive = true,
        public ?string $deletionRunId = null,
    ) {}
}
