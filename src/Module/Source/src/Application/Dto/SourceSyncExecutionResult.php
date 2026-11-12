<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

use Packio\Source\Application\Service\SourceSyncOutcome;

final readonly class SourceSyncExecutionResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public SourceSyncOutcome $outcome,
        public array $data = [],
        public ?string $error = null,
    ) {}
}
