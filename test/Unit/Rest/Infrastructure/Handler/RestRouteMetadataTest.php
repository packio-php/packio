<?php

declare(strict_types=1);

namespace PackioTest\Unit\Rest\Infrastructure\Handler;

use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Rest\Attribute\ApiAudited;
use Packio\Rest\Attribute\ApiAuthenticated;
use Packio\Rest\Attribute\ApiOrganizationContext;
use Packio\Rest\Attribute\ApiScope;
use Packio\Rest\Infrastructure\Handler\ApiHealthHandler;
use Packio\Rest\Infrastructure\Handler\OpenApiJsonHandler;
use Packio\Rest\Infrastructure\Handler\SourceProvidersIndexHandler;
use Packio\Rest\Infrastructure\OpenApi\OpenApiSpecGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Route;

use function array_any;
use function array_intersect;
use function array_keys;
use function basename;
use function class_exists;
use function dirname;
use function glob;
use function in_array;
use function preg_replace;
use function sort;
use function str_starts_with;
use function strtolower;

final class RestRouteMetadataTest extends TestCase
{
    private const array PUBLIC_HANDLERS = [ApiHealthHandler::class, SourceProvidersIndexHandler::class, OpenApiJsonHandler::class];

    #[Test]
    public function apiRoutesRequireAuthenticationAndScope(): void
    {
        $missing = [];

        foreach ($this->routes() as [$class, $route]) {
            if (! str_starts_with($route->path, '/api/v1/') || in_array($class, self::PUBLIC_HANDLERS, true)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ([] === $reflection->getAttributes(ApiAuthenticated::class) || [] === $reflection->getAttributes(ApiScope::class)) {
                $missing[$class] = $class;
            }
        }

        self::assertSame([], array_keys($missing), 'Every /api/v1 route must declare #[ApiAuthenticated] and #[ApiScope].');
    }

    #[Test]
    public function writeRoutesAreAuditedAndRateLimited(): void
    {
        $missing = [];

        foreach ($this->routes() as [$class, $route]) {
            if ([] === array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods ?? [])) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ([] === $reflection->getAttributes(RateLimit::class)
                || ([] === $reflection->getAttributes(ApiAudited::class) && [] === $reflection->getAttributes(ApiOrganizationContext::class))
            ) {
                $missing[$class] = $class;
            }
        }

        self::assertSame([], array_keys($missing), 'Every write route must declare #[RateLimit] and #[ApiAudited] or #[ApiOrganizationContext].');
    }

    #[Test]
    public function adminRoutesRequireAdminScopeAndPermission(): void
    {
        $missing = [];

        foreach ($this->routes() as [$class, $route]) {
            if (! str_starts_with($route->path, '/api/v1/admin/')) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $hasScope   = array_any($reflection->getAttributes(ApiScope::class), static fn (ReflectionAttribute $reflectionAttribute): bool => ['admin'] === $reflectionAttribute->getArguments());
            $hasCan     = array_any($reflection->getAttributes(Can::class), static fn (ReflectionAttribute $reflectionAttribute): bool => 'admin.access' === $reflectionAttribute->newInstance()->permission);

            if (! $hasScope || ! $hasCan) {
                $missing[$class] = $class;
            }
        }

        self::assertSame([], array_keys($missing), 'Every /api/v1/admin route must declare #[ApiScope("admin")] and #[Can("admin.access")].');
    }

    #[Test]
    public function everyRouteIsDocumented(): void
    {
        $routes = [];
        foreach ($this->routes() as [, $route]) {
            if (! str_starts_with($route->path, '/api/v1/') || '/api/v1/doc.json' === $route->path) {
                continue;
            }

            foreach ($route->methods ?? [] as $method) {
                $routes[] = strtolower($method) . ' ' . preg_replace('/:(\w+)/', '{$1}', $route->path);
            }
        }

        $documented = [];
        foreach (new OpenApiSpecGenerator()->generate(true)['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $documented[] = $method . ' ' . $path;
                }
            }
        }

        sort($routes);
        sort($documented);

        self::assertSame($documented, $routes, 'Routes and OpenAPI operations must match.');
    }

    /**
     * @return list<array{class-string, Route}>
     */
    private function routes(): array
    {
        $routes = [];

        foreach (glob(dirname(__DIR__, 5) . '/src/Module/Rest/src/Infrastructure/Handler/*.php') ?: [] as $file) {
            $class = 'Packio\Rest\Infrastructure\Handler\\' . basename($file, '.php');
            self::assertTrue(class_exists($class));

            foreach (new ReflectionClass($class)->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $routes[] = [$class, $attribute->newInstance()];
            }
        }

        return $routes;
    }
}
