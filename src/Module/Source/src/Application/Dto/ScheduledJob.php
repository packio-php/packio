<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

final readonly class ScheduledJob
{
    public function __construct(public string $id, public bool $terminal) {}
}
