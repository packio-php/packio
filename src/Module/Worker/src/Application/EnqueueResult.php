<?php

declare(strict_types=1);

namespace Packio\Worker\Application;

use Packio\Worker\Domain\Entity\Job;

final readonly class EnqueueResult
{
    public function __construct(public Job $job, public bool $created) {}
}
