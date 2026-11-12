<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Packio\Application\Validation\InvalidMappedRequestValidationPlan;
use Packio\Application\Validation\MappedRequestValidationPlanResolver;
use Packio\Application\Validation\ValidationFailureResponseDispatcher;
use Packio\Application\Validation\ValidationResultDetailsNormalizer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Validator\ValidatorInterface;

use function is_object;

/**
 * Validates every explicitly selected mapped DTO target, in declaration order.
 *
 * The route must carry `mapped_request_validations` metadata produced by
 * `#[ValidateMappedRequest]`; mapper-only routes must not resolve this middleware.
 * A missing or non-object mapped attribute after a successful mapping stage is a
 * configuration/runtime invariant violation and fails closed.
 */
final readonly class ValidateMappedRequestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ValidatorInterface $validator,
        private MappedRequestValidationPlanResolver $mappedRequestValidationPlanResolver,
        private ValidationResultDetailsNormalizer $validationResultDetailsNormalizer,
        private ValidationFailureResponseDispatcher $validationFailureResponseDispatcher,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $plan = $this->mappedRequestValidationPlanResolver->resolve($request);

        foreach ($plan as $item) {
            $target = $request->getAttribute($item->targetAttribute);
            if (! is_object($target)) {
                throw InvalidMappedRequestValidationPlan::missingTarget($item->targetAttribute);
            }

            $result = $this->validator->validate($target);
            if (! $result->isValid()) {
                return $this->validationFailureResponseDispatcher->dispatch(
                    $request,
                    $item,
                    $this->validationResultDetailsNormalizer->normalize($result),
                );
            }
        }

        return $handler->handle($request);
    }
}
