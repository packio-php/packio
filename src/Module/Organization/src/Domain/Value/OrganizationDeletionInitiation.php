<?php

declare(strict_types=1);

namespace Packio\Organization\Domain\Value;

/**
 * Result of an atomic deletion-initiation attempt. `previousActive` carries
 * the pre-transition activity flag so a compensating revert can restore the
 * organization to its exact prior state.
 */
final readonly class OrganizationDeletionInitiation
{
    public function __construct(
        public OrganizationDeletionInitiationOutcome $outcome,
        public bool $previousActive = true,
        public ?string $deletionRunId = null,
    ) {}
}
