<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Value;

/**
 * Result of an atomic deletion-initiation attempt. `previousActive` carries
 * the pre-transition activity flag so a compensating revert can restore the
 * source to its exact prior state.
 */
final readonly class SourceDeletionInitiation
{
    public function __construct(
        public SourceDeletionInitiationOutcome $outcome,
        public bool $previousActive = true,
        public ?string $deletionRunId = null,
    ) {}
}
