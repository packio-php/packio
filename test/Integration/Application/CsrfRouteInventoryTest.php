<?php

declare(strict_types=1);

namespace PackioTest\Integration\Application;

use Laminas\ServiceManager\ServiceManager;
use Packio\Application\Routing\AuthenticationRouteOptions;
use Packio\Application\Routing\CsrfRouteOptions;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Webhook\Infrastructure\Handler\ReceiveWebhookHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Routing\Attributes\Discovery\DiscoveredClassesResolverInterface;
use Sirix\Mezzio\Routing\Attributes\DuplicateRouteResolver;
use Sirix\Mezzio\Routing\Attributes\Extractor\AttributeRouteExtractorInterface;
use Sirix\Mezzio\Routing\Attributes\RouteDefinition;
use Sirix\Mezzio\Routing\Attributes\RouteDefinitionResolver;

use function array_diff;
use function array_filter;
use function class_exists;
use function dirname;
use function str_starts_with;

final class CsrfRouteInventoryTest extends TestCase
{
    #[Test]
    public function unsafeRoutesHaveOnlyExplicitCsrfPolicies(): void
    {
        $routes = $this->routeDefinitions();

        $unsafeRoutes = array_filter(
            $routes,
            static fn (RouteDefinition $routeDefinition): bool => null === $routeDefinition->methods
                || [] !== array_diff($routeDefinition->methods, ['GET', 'HEAD', 'OPTIONS']),
        );
        self::assertNotEmpty($unsafeRoutes);

        foreach ($unsafeRoutes as $unsafeRoute) {
            $routeName  = $unsafeRoute->name ?? $unsafeRoute->path;
            $csrfExempt = $unsafeRoute->defaults[CsrfRouteOptions::EXEMPT] ?? false;

            if (ReceiveWebhookHandler::class === $unsafeRoute->handlerService) {
                self::assertTrue($csrfExempt, $routeName);

                continue;
            }

            if (AuthenticationRouteOptions::API_PROFILE === ($unsafeRoute->defaults[AuthenticationRouteOptions::PROFILE] ?? null)) {
                self::assertTrue($csrfExempt, $routeName);
                self::assertTrue(str_starts_with($unsafeRoute->path, '/api/v1/'), $routeName);
                self::assertTrue($this->hasApiAuthenticationPolicy($unsafeRoute), $routeName);

                continue;
            }

            self::assertFalse($csrfExempt, $routeName);
        }

        $routeDefinition = $this->routeForHandler($routes, ReceiveWebhookHandler::class);
        self::assertTrue($routeDefinition->defaults[CsrfRouteOptions::EXEMPT] ?? false);
    }

    /** @return list<RouteDefinition> */
    private function routeDefinitions(): array
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 3) . '/config/config.php';

        /** @var array<string, mixed> $dependencies */
        $dependencies                       = $config['dependencies'];
        $dependencies['services']['config'] = $config;
        $container                          = new ServiceManager($dependencies);

        return new RouteDefinitionResolver(
            $container->get(AttributeRouteExtractorInterface::class),
            $container->get(DuplicateRouteResolver::class),
            $container->get(DiscoveredClassesResolverInterface::class),
        )->resolve([]);
    }

    /** @param list<RouteDefinition> $routes */
    private function routeForHandler(array $routes, string $handler): RouteDefinition
    {
        foreach ($routes as $route) {
            if ($route->handlerService === $handler) {
                return $route;
            }
        }

        self::fail('Route definition was not discovered for ' . $handler);
    }

    private function hasApiAuthenticationPolicy(RouteDefinition $routeDefinition): bool
    {
        return class_exists($routeDefinition->handlerService)
            && [] !== new ReflectionClass($routeDefinition->handlerService)->getAttributes(ApiAuthenticated::class);
    }
}
