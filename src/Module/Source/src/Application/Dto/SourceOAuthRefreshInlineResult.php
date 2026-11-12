<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

use function count;

final readonly class SourceOAuthRefreshInlineResult
{
    /**
     * @param list<SourceOAuthRefreshCandidate>                                  $candidates
     * @param list<SourceOAuthRefreshExecutionResult>                            $refreshed
     * @param list<SourceOAuthRefreshCandidate>                                  $orphaned
     * @param list<array{candidate: SourceOAuthRefreshCandidate, error: string}> $failed
     */
    public function __construct(
        public array $candidates,
        public array $refreshed,
        public array $orphaned,
        public array $failed,
    ) {}

    public function candidateCount(): int
    {
        return count($this->candidates);
    }

    public function refreshedCount(): int
    {
        return count($this->refreshed);
    }

    public function orphanedCount(): int
    {
        return count($this->orphaned);
    }

    public function failedCount(): int
    {
        return count($this->failed);
    }
}
