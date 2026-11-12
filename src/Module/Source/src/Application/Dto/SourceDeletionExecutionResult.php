<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

use Packio\Source\Application\Service\SourceDeletionOutcome;

final readonly class SourceDeletionExecutionResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public SourceDeletionOutcome $outcome,
        public array $data = [],
        public ?string $error = null,
    ) {}
}
