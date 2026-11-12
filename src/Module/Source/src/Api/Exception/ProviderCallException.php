<?php

declare(strict_types=1);

namespace Packio\Source\Api\Exception;

use RuntimeException;
use Throwable;

final class ProviderCallException extends RuntimeException
{
    public function __construct(public readonly ProviderFailure $failure, ?Throwable $previous = null)
    {
        parent::__construct($failure->value, 0, $previous);
    }
}
