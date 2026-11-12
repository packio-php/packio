<?php

declare(strict_types=1);

namespace PackioTest\Unit\Admin\Infrastructure\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use SplFileInfo;

use function array_any;
use function class_exists;
use function dirname;
use function file_get_contents;
use function is_dir;
use function preg_match;
use function sort;
use function str_ends_with;
use function str_starts_with;
use function trim;

final class AdminRouteAuthorizationMetadataTest extends TestCase
{
    #[Test]
    public function adminRoutesDeclareAuthenticationAndAdminRbacAuthorization(): void
    {
        $missingAuthentication     = [];
        $missingAdminAuthorization = [];

        foreach ($this->adminHandlerClasses() as $handlerClass) {
            $reflectionClass = new ReflectionClass($handlerClass);

            if (! $this->isAdminRoute($reflectionClass)) {
                continue;
            }

            if ([] === $reflectionClass->getAttributes(Authenticated::class)) {
                $missingAuthentication[] = $handlerClass;
            }

            if (! $this->hasAdminAccessAuthorization($reflectionClass)) {
                $missingAdminAuthorization[] = $handlerClass;
            }
        }

        self::assertSame([], $missingAuthentication, 'Every /admin route must declare #[Authenticated].');
        self::assertSame([], $missingAdminAuthorization, 'Every /admin route must declare #[Can("admin.access")].');
    }

    /**
     * @return list<class-string>
     */
    private function adminHandlerClasses(): array
    {
        $path    = dirname(__DIR__, 5) . '/src/Module/Admin/src/Infrastructure/Handler';
        $classes = [];

        if (! is_dir($path)) {
            return [];
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

            $className = $this->classNameFromFile($fileInfo->getPathname());
            if (null === $className) {
                continue;
            }

            $classes[] = $className;
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
    private function isAdminRoute(ReflectionClass $reflectionClass): bool
    {
        foreach ([
            ...$reflectionClass->getAttributes(Get::class),
            ...$reflectionClass->getAttributes(Post::class),
        ] as $attribute) {
            $instance = $attribute->newInstance();

            if (str_starts_with($instance->path, '/admin')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    private function hasAdminAccessAuthorization(ReflectionClass $reflectionClass): bool
    {
        return array_any($reflectionClass->getAttributes(Can::class), fn ($attribute) => 'admin.access' === $attribute->newInstance()->permission);
    }
}
