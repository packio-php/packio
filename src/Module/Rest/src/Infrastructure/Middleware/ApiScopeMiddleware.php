<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Middleware;

use Mezzio\Router\RouteResult;
use Packio\Identity\Api\Dto\ApiAccessTokenDto;
use Packio\Rest\Application\Security\ApiScopeEvaluator;
use Packio\Rest\Infrastructure\Http\ApiRequestAttributes;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_string;

final readonly class ApiScopeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ApiScopeEvaluator $apiScopeEvaluator,
        private ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requiredScope = $this->requiredScope($request);
        if (null === $requiredScope) {
            return $handler->handle($request);
        }

        $token = $request->getAttribute(ApiRequestAttributes::ACCESS_TOKEN);
        if (! $token instanceof ApiAccessTokenDto) {
            return $this->apiErrorResponseFactory->unauthorized();
        }

        if (! $this->apiScopeEvaluator->allows($requiredScope, ApiRequestAttributes::effectiveScopes($request, $token))) {
            return $this->apiErrorResponseFactory->forbidden('API token scope is not sufficient.');
        }

        return $handler->handle($request);
    }

    private function requiredScope(ServerRequestInterface $serverRequest): ?string
    {
        $scope = $serverRequest->getAttribute(ApiRequestAttributes::REQUIRED_SCOPE);
        if (is_string($scope) && '' !== $scope) {
            return $scope;
        }

        $routeResult = $serverRequest->getAttribute(RouteResult::class);
        if (! $routeResult instanceof RouteResult || ! $routeResult->isSuccess()) {
            return null;
        }

        $route = $routeResult->getMatchedRoute();
        if (false === $route) {
            return null;
        }

        $scope = $route->getOptions()[ApiRequestAttributes::REQUIRED_SCOPE] ?? null;

        return is_string($scope) && '' !== $scope ? $scope : null;
    }
}
