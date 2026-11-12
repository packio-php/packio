<?php

declare(strict_types=1);

namespace Packio\Rest\Attribute;

use Attribute;
use Packio\Rest\Infrastructure\Http\ApiRequestAttributes;
use Packio\Rest\Infrastructure\Middleware\ApiScopeMiddleware;
use Sirix\Mezzio\Routing\Contracts\RouteAttributeModifierInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class ApiScope implements RouteAttributeModifierInterface
{
    public function __construct(private string $scope) {}

    public function getMiddleware(): array
    {
        return [ApiScopeMiddleware::class];
    }

    public function getDefaults(): array
    {
        return [
            ApiRequestAttributes::REQUIRED_SCOPE => $this->scope,
        ];
    }
}
