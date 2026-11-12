<?php

declare(strict_types=1);

namespace Packio\Application\Runtime;

use RuntimeException;
use Throwable;

final class ApplicationRequestFailedException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('The HTTP application failed to handle the request.', 0, $previous);
    }
}
