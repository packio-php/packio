<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Application-owned response port for canonical semantic validation failures.
 *
 * Adapters decide the concrete envelope; Application code must not depend on the
 * Api module.
 */
interface ValidationFailureResponseFactoryInterface
{
    /**
     * @param array<string, list<string>> $details
     */
    public function validation(
        ServerRequestInterface $serverRequest,
        array $details,
        string $message = 'Invalid request data.',
        int $status = 422
    ): ResponseInterface;
}
