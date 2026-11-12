<?php

declare(strict_types=1);

namespace PackioTest\Unit\Package\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Package\Infrastructure\Handler\PackageDetailsHandler;
use Packio\Package\Infrastructure\Handler\PackagesHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[CoversClass(PackagesHandler::class)]
#[CoversClass(PackageDetailsHandler::class)]
final class PackageViewHandlerTest extends TestCase
{
    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('packageViewHandlerClasses')]
    public function packageViewRoutesUsePackagesViewPermissionWithOrganizationMembershipContext(string $handlerClass): void
    {
        $reflectionClass = new ReflectionClass($handlerClass);
        $canAttributes   = $reflectionClass->getAttributes(Can::class);

        self::assertCount(1, $canAttributes);

        /** @var Can $can */
        $can = $canAttributes[0]->newInstance();

        self::assertSame('packages.view', $can->permission);
        self::assertSame([
            'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
            'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
        ], $can->context);
    }

    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('packageViewHandlerClasses')]
    public function packageViewRoutesKeepOrganizationExistenceGuard(string $handlerClass): void
    {
        $reflectionClass = new ReflectionClass($handlerClass);
        $getAttributes   = $reflectionClass->getAttributes(Get::class);

        self::assertCount(1, $getAttributes);

        /** @var Get $get */
        $get = $getAttributes[0]->newInstance();

        self::assertSame([RequireExistingOrganizationMiddleware::class], $get->middleware);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function packageViewHandlerClasses(): iterable
    {
        yield 'packages index' => [PackagesHandler::class];

        yield 'package details' => [PackageDetailsHandler::class];
    }
}
