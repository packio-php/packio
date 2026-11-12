<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown when a token refresh attempt fails due to provider error.
 */
final class SourceTokenRefreshFailedException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly bool $requiresReauthorization = true,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
