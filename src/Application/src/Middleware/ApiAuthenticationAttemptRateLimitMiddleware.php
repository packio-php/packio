<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Routing\AuthenticationRouteOptions;
use Packio\Application\Security\RateLimiter\ApiAuthenticationAttemptLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;

/**
 * Blocks only credential-bearing API requests after prior failed attempts. It does
 * not consume an authentication-attempt quota itself.
 */
final readonly class ApiAuthenticationAttemptRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthenticationProfileProviderInterface $authenticationProfileProvider,
        private ApiAuthenticationAttemptLimiter $apiAuthenticationAttemptLimiter,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (AuthenticationRouteOptions::API_PROFILE !== AuthenticationRouteOptions::profile($request)) {
            return $handler->handle($request);
        }

        if (null === $this->authenticationProfileProvider->get(AuthenticationRouteOptions::API_PROFILE)->transport()->fetch($request)
            || ! $this->apiAuthenticationAttemptLimiter->isBlocked($request)) {
            return $handler->handle($request);
        }

        return new JsonResponse(
            [
                'error'  => 'Too many API authentication attempts. Please try again later.',
                'status' => 429,
            ],
            429,
            [
                'Retry-After' => (string) $this->apiAuthenticationAttemptLimiter->retryAfterSeconds(),
            ],
        );
    }
}
