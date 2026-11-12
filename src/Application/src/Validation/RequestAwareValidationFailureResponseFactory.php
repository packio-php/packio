<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Selects the envelope factory for the current request: the API adapter for
 * `/api/*` requests and the JSON fallback adapter otherwise.
 *
 * The composite itself implements the application response port, so the
 * dispatcher stays free of module-specific envelope knowledge.
 */
final readonly class RequestAwareValidationFailureResponseFactory implements ValidationFailureResponseFactoryInterface
{
    public function __construct(
        private ValidationFailureResponseFactoryInterface $jsonValidationFailureResponseFactory,
        private ApiValidationFailureResponseFactoryInterface $apiValidationFailureResponseFactory,
    ) {}

    /**
     * @param array<string, list<string>> $details
     */
    public function validation(
        ServerRequestInterface $serverRequest,
        array $details,
        string $message = 'Invalid request data.',
        int $status = 422
    ): ResponseInterface {
        $factory = ApiPathMatcher::isApiRequest($serverRequest)
            ? $this->apiValidationFailureResponseFactory
            : $this->jsonValidationFailureResponseFactory;

        return $factory->validation($serverRequest, $details, $message, $status);
    }
}
