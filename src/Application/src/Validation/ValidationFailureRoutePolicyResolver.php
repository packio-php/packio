<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use Mezzio\Router\RouteResult;
use Psr\Http\Message\ServerRequestInterface;

use function array_first;
use function is_array;

final class ValidationFailureRoutePolicyResolver
{
    /**
     * The first `validation_failed` policy applies to the DTO of the first mapping only:
     * a mapping error of any other requested attribute gets no route policy.
     */
    public function resolve(
        ServerRequestInterface $serverRequest,
        ?string $requestedTargetAttribute = null,
    ): ?ValidationFailureRoutePolicy {
        $routeResult = $serverRequest->getAttribute(RouteResult::class);
        if (! $routeResult instanceof RouteResult || ! $routeResult->isSuccess()) {
            return null;
        }

        $matchedRoute = $routeResult->getMatchedRoute();
        if (false === $matchedRoute) {
            return null;
        }

        $options  = $matchedRoute->getOptions();
        $rules    = $options['validation_failed'] ?? null;
        $mappings = $options['valinor_mappings'] ?? null;
        $rule     = is_array($rules) ? array_first($rules) : null;
        if (! is_array($rule) || ! is_array($mappings)) {
            return null;
        }

        $targetAttribute = MappedRequestValidationPlanResolver::mappingDto(array_first($mappings));
        if (null === $targetAttribute) {
            return null;
        }

        if (null !== $requestedTargetAttribute && $targetAttribute !== $requestedTargetAttribute) {
            return null;
        }

        return new ValidationFailureRoutePolicy($targetAttribute, $rule);
    }
}
