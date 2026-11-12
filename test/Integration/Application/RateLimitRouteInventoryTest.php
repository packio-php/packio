<?php

declare(strict_types=1);

namespace PackioTest\Integration\Application;

use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Attributes\RouteDefinitionResolver;

use function dirname;
use function is_array;
use function sprintf;

final class RateLimitRouteInventoryTest extends TestCase
{
    #[Test]
    public function everyRouteRateLimitHasAPolicy(): void
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 3) . '/config/config.php';

        /** @var array<string, mixed> $rateLimits */
        $rateLimits = $config['security']['rate_limits'];
        $checked    = 0;

        foreach ($this->routeDefinitions($config) as $route) {
            /** @var list<array<string, mixed>> $rules */
            $rules = $route->defaults['rate_limit'] ?? [];

            foreach ($rules as $rule) {
                ++$checked;
                self::assertTrue(
                    is_array($rateLimits[$rule['key']] ?? null),
                    sprintf('%s: rate limit "%s" has no policy in security.rate_limits', $route->name ?? $route->path, $rule['key']),
                );
            }
        }

        self::assertNotEmpty($checked);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<RouteDefinition>
     */
    private function routeDefinitions(array $config): array
    {
        /** @var array<string, mixed> $dependencies */
        $dependencies                            = $config['dependencies'];
        $dependencies['services']['config']      = $config;
        $serviceManager                          = new ServiceManager($dependencies);

        return new RouteDefinitionResolver(
            $serviceManager->get(AttributeRouteExtractorInterface::class),
            $serviceManager->get(DuplicateRouteResolver::class),
            $serviceManager->get(DiscoveredClassesResolverInterface::class),
        )->resolve([]);
    }
}
