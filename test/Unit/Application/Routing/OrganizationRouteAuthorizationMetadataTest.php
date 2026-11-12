<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Routing;

use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use SplFileInfo;

use function array_map;
use function class_exists;
use function dirname;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function preg_match;
use function property_exists;
use function sort;
use function str_contains;
use function str_ends_with;
use function trim;

final class OrganizationRouteAuthorizationMetadataTest extends TestCase
{
    #[Test]
    public function routesWithOrganizationExistenceGuardDeclareRbacAuthorization(): void
    {
        $missingAuthorization = [];

        foreach ($this->handlerClasses() as $handlerClass) {
            $reflectionClass = new ReflectionClass($handlerClass);

            if (! $this->usesOrganizationExistenceGuard($reflectionClass)) {
                continue;
            }

            if ([] !== $reflectionClass->getAttributes(Can::class)) {
                continue;
            }

            $missingAuthorization[] = $handlerClass;
        }

        self::assertSame(
            [],
            $missingAuthorization,
            'Every route using RequireExistingOrganizationMiddleware must also declare #[Can(...)] authorization.',
        );
    }

    /**
     * @return list<class-string>
     */
    private function handlerClasses(): array
    {
        $projectRoot = dirname(__DIR__, 4);
        $classes     = [];

        foreach ([
            $projectRoot . '/src/Application/src/Infrastructure/Handler',
            $projectRoot . '/src/Module',
        ] as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($iterator as $fileInfo) {
                if (! $fileInfo instanceof SplFileInfo) {
                    continue;
                }

                if (! $fileInfo->isFile()) {
                    continue;
                }

                if (! str_ends_with($fileInfo->getPathname(), '.php')) {
                    continue;
                }

                if (! str_contains($fileInfo->getPathname(), '/Infrastructure/Handler/')) {
                    continue;
                }

                $className = $this->classNameFromFile($fileInfo->getPathname());
                if (null === $className) {
                    continue;
                }

                if (! class_exists($className)) {
                    continue;
                }

                $classes[] = $className;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return null|class-string
     */
    private function classNameFromFile(string $path): ?string
    {
        $contents = file_get_contents($path);
        if (false === $contents) {
            return null;
        }

        if (1 !== preg_match('/^namespace\s+([^;]+);/m', $contents, $namespaceMatch)) {
            return null;
        }

        if (1 !== preg_match('/^(?:final\s+)?(?:readonly\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $contents, $classMatch)) {
            return null;
        }

        $className = trim($namespaceMatch[1]) . '\\' . trim($classMatch[1]);

        return class_exists($className) ? $className : null;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    private function usesOrganizationExistenceGuard(ReflectionClass $reflectionClass): bool
    {
        foreach ($reflectionClass->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();

            if (! property_exists($instance, 'middleware')) {
                continue;
            }

            $middleware = $instance->middleware;
            if (is_string($middleware)) {
                $middleware = [$middleware];
            }

            if (! is_array($middleware)) {
                continue;
            }

            $middleware = array_map(static fn (mixed $entry): string => is_string($entry) ? $entry : '', $middleware);
            if (in_array(RequireExistingOrganizationMiddleware::class, $middleware, true)) {
                return true;
            }
        }

        return false;
    }
}
