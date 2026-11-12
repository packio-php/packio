<?php

declare(strict_types=1);

namespace Packio\Application\Runtime;

use RuntimeException;
use Throwable;

final class MalformedRoadRunnerRequestException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('RoadRunner supplied a malformed HTTP request.', 0, $previous);
    }
}
