<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Validation;

use Packio\Application\Validation\ApiValidationFailureResponseFactoryInterface;
use Packio\Rest\Infrastructure\Request\CreateSourceRequest;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ApiValidationFailureResponseFactory implements ApiValidationFailureResponseFactoryInterface
{
    public function __construct(
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function validation(
        ServerRequestInterface $serverRequest,
        array $details,
        string $message = 'Invalid request data.',
        int $status = 422
    ): ResponseInterface {
        // Source creation historically includes both required keys, even when one is valid.
        if ($serverRequest->getAttribute(CreateSourceRequest::class) instanceof CreateSourceRequest
            && (isset($details['provider']) || isset($details['resource']))) {
            $details = [
                'provider' => $details['provider'] ?? [],
                'resource' => $details['resource'] ?? [],
            ];
        }

        return $this->apiErrorResponseFactory->error('validation_failed', $message, $status, $details);
    }
}
