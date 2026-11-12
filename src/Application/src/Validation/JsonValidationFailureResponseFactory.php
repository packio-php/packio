<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Application default adapter: flat non-API JSON contract preserved from the
 * existing non-API JSON dispatcher behavior.
 */
final readonly class JsonValidationFailureResponseFactory implements ValidationFailureResponseFactoryInterface
{
    public function validation(
        ServerRequestInterface $serverRequest,
        array $details,
        string $message = 'Invalid request data.',
        int $status = 422
    ): ResponseInterface {
        return new JsonResponse([
            'error'    => $message,
            'messages' => $details,
        ], $status);
    }
}
