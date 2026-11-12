<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

use function count;

final readonly class SourceOAuthRefreshScheduleResult
{
    /**
     * @param list<SourceOAuthRefreshCandidate> $candidates
     * @param list<SourceOAuthRefreshCandidate> $scheduled
     * @param list<SourceOAuthRefreshCandidate> $orphaned
     */
    public function __construct(
        public array $candidates,
        public array $scheduled,
        public array $orphaned,
        public bool $dryRun,
    ) {}

    public function candidateCount(): int
    {
        return count($this->candidates);
    }

    public function scheduledCount(): int
    {
        return count($this->scheduled);
    }

    public function orphanedCount(): int
    {
        return count($this->orphaned);
    }
}
