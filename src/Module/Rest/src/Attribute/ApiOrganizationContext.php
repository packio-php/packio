<?php

declare(strict_types=1);

namespace Packio\Rest\Attribute;

use Attribute;
use Packio\Rest\Infrastructure\Middleware\ApiAuditMiddleware;
use Packio\Rest\Infrastructure\Middleware\ApiOrganizationContextMiddleware;
use Sirix\Mezzio\Routing\Contracts\AggregatingRouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiOrganizationContext implements AggregatingRouteAttributeModifierInterface
{
    public function getMiddleware(): array
    {
        return [
            ApiOrganizationContextMiddleware::class,
        ];
    }

    public function mergeDefaults(array $defaults): array
    {
        return $defaults;
    }

    public function getUniqueMiddleware(): array
    {
        return [
            'packio.api.audit' => ApiAuditMiddleware::class,
        ];
    }

    public function getDefaults(): array
    {
        return [];
    }
}
