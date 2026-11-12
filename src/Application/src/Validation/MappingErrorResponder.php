<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use Packio\Application\Middleware\StripPackageMappingErrorInertiaDecorationMiddleware;
use Psr\Http\Message\ResponseInterface;
use Sirix\Mezzio\Valinor\Error\DefaultMappingErrorResponder;
use Sirix\Mezzio\Valinor\Error\MappingErrorContext;
use Sirix\Mezzio\Valinor\Error\MappingErrorResponderInterface;

final readonly class MappingErrorResponder implements MappingErrorResponderInterface
{
    public function __construct(
        private ValidationFailureResponseFactoryInterface $validationFailureResponseFactory,
        private ValidationFailureRequestPolicy $validationFailureRequestPolicy,
        private ValidationFailureRoutePolicyResolver $validationFailureRoutePolicyResolver,
        private ValidationFailureResponseDispatcher $validationFailureResponseDispatcher,
        private MappingErrorDetailsNormalizer $mappingErrorDetailsNormalizer,
        private DefaultMappingErrorResponder $defaultMappingErrorResponder,
    ) {}

    public function respond(MappingErrorContext $context): ResponseInterface
    {
        $request = $context->request;
        $details = $this->mappingErrorDetailsNormalizer->normalize($context->error);

        if ($this->validationFailureRequestPolicy->isApiPath($request)) {
            return $this->validationFailureResponseFactory->validation($request, $details);
        }

        $policy = $this->validationFailureRoutePolicyResolver->resolve($request, $context->requestAttributeKey);
        if ($policy instanceof ValidationFailureRoutePolicy) {
            return $this->validationFailureResponseDispatcher->dispatch(
                $request,
                new ValidationTargetPlanItem($policy->targetAttribute, $policy->options),
                $details,
            );
        }

        return $this->defaultMappingErrorResponder
            ->respond($context)
            ->withHeader(StripPackageMappingErrorInertiaDecorationMiddleware::INERTIA_FALLBACK_RESPONSE_MARKER, 'true')
        ;
    }
}
