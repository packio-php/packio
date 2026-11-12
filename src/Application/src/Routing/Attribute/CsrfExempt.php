<?php

declare(strict_types=1);

namespace Packio\Application\Routing\Attribute;

use Attribute;
use Packio\Application\Routing\CsrfRouteOptions;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

/**
 * Marks a stateless or independently authenticated endpoint as exempt from
 * the browser session CSRF policy.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class CsrfExempt implements RouteAttributeModifierInterface
{
    public function getMiddleware(): array
    {
        return [];
    }

    public function getDefaults(): array
    {
        return [
            CsrfRouteOptions::EXEMPT => true,
        ];
    }
}
