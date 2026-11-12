<?php

declare(strict_types=1);

namespace Packio\Package\Application\Deletion;

final readonly class PackageDeletionResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public PackageDeletionOutcome $outcome,
        public array $data,
        public string $message,
    ) {}
}
