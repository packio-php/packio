<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

final readonly class ArchiveSchedulingResult
{
    public function __construct(public int $scheduled, public int $failed) {}

    public function isDegraded(): bool
    {
        return $this->failed > 0;
    }
}
