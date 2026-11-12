<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

/**
 * Marker port for the Api module's semantic-validation envelope factory.
 *
 * Application code never references the Api implementation directly; the API
 * module registers its adapter under this marker and the composite default
 * response factory selects it for `/api/*` requests.
 */
interface ApiValidationFailureResponseFactoryInterface extends ValidationFailureResponseFactoryInterface {}
