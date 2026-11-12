<?php

declare(strict_types=1);

namespace Packio\Application\Runtime;

use RuntimeException;
use Throwable;

final class RoadRunnerProtocolException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('RoadRunner protocol communication failed.', 0, $previous);
    }
}
