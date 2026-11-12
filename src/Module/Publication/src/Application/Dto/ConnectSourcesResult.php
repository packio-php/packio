<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Dto;

final readonly class ConnectSourcesResult
{
    /**
     * @param list<ConnectSourceOutcome> $queued
     * @param list<ConnectSourceOutcome> $skipped
     * @param list<ConnectSourceOutcome> $failed
     */
    public function __construct(
        public array $queued = [],
        public array $skipped = [],
        public array $failed = [],
        public ?string $correlationId = null
    ) {}
}
