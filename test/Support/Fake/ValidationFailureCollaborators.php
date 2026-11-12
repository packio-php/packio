<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Middleware\ValidateMappedRequestMiddleware;
use Packio\Application\Security\Http\TrustedProxyRequestOriginResolver;
use Packio\Application\Validation\JsonValidationFailureResponseFactory;
use Packio\Application\Validation\MappedRequestValidationPlanResolver;
use Packio\Application\Validation\RequestAwareValidationFailureResponseFactory;
use Packio\Application\Validation\ValidationFailureRequestPolicy;
use Packio\Application\Validation\ValidationFailureResponseDispatcher;
use Packio\Application\Validation\ValidationFailureResponseFactoryInterface;
use Packio\Application\Validation\ValidationResultDetailsNormalizer;
use Packio\Rest\Infrastructure\Response\ApiErrorResponseFactory;
use Packio\Rest\Infrastructure\Validation\ApiValidationFailureResponseFactory;
use Yiisoft\Validator\ValidatorInterface;

/**
 * Validation-failure collaborators wired the way the production container wires them.
 */
trait ValidationFailureCollaborators
{
    private static function requestAwareValidationFailureResponseFactory(): RequestAwareValidationFailureResponseFactory
    {
        return new RequestAwareValidationFailureResponseFactory(
            new JsonValidationFailureResponseFactory(),
            new ApiValidationFailureResponseFactory(new ApiErrorResponseFactory()),
        );
    }

    private static function validationFailureResponseDispatcher(
        ValidationRedirectState $validationRedirectState = new ValidationRedirectState(),
        ?ValidationFailureResponseFactoryInterface $validationFailureResponseFactory = null,
        TrustedProxyRequestOriginResolver $trustedProxyRequestOriginResolver = new TrustedProxyRequestOriginResolver(),
    ): ValidationFailureResponseDispatcher {
        return new ValidationFailureResponseDispatcher(
            new InertiaNotificationMessenger(),
            $validationRedirectState,
            new ValidationFailureRequestPolicy(),
            $validationFailureResponseFactory ?? self::requestAwareValidationFailureResponseFactory(),
            $trustedProxyRequestOriginResolver,
        );
    }

    private static function validateMappedRequestMiddleware(
        ValidatorInterface $validator,
        ?ValidationFailureResponseDispatcher $validationFailureResponseDispatcher = null,
    ): ValidateMappedRequestMiddleware {
        return new ValidateMappedRequestMiddleware(
            $validator,
            new MappedRequestValidationPlanResolver(),
            new ValidationResultDetailsNormalizer(),
            $validationFailureResponseDispatcher ?? self::validationFailureResponseDispatcher(),
        );
    }
}
