<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Infrastructure\Handler;

use Packio\Application\Infrastructure\Handler\HomeHandler;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[CoversClass(HomeHandler::class)]
final class HomeHandlerTest extends TestCase
{
    #[Test]
    public function routeUsesPackagesViewPermissionWithOrganizationMembershipContext(): void
    {
        $reflectionClass = new ReflectionClass(HomeHandler::class);
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

    #[Test]
    public function routeKeepsOrganizationExistenceGuard(): void
    {
        $reflectionClass = new ReflectionClass(HomeHandler::class);
        $getAttributes   = $reflectionClass->getAttributes(Get::class);

        self::assertCount(1, $getAttributes);

        /** @var Get $get */
        $get = $getAttributes[0]->newInstance();

        self::assertSame([RequireExistingOrganizationMiddleware::class], $get->middleware);
    }
}
