<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Organization\Infrastructure\Handler\OrganizationMembersHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationSettingsHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[CoversClass(OrganizationSettingsHandler::class)]
#[CoversClass(OrganizationMembersHandler::class)]
final class OrganizationViewHandlerTest extends TestCase
{
    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('memberViewHandlerClasses')]
    public function memberViewRoutesUseExpectedPermissionWithRequestContext(string $handlerClass, string $expectedPermission): void
    {
        $reflectionClass = new ReflectionClass($handlerClass);
        $canAttributes   = $reflectionClass->getAttributes(Can::class);

        self::assertCount(1, $canAttributes);

        /** @var Can $can */
        $can = $canAttributes[0]->newInstance();

        self::assertSame($expectedPermission, $can->permission);
        self::assertSame([
            'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
            'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
        ], $can->context);
    }

    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('memberViewHandlerClasses')]
    public function memberViewRoutesKeepOrganizationExistenceGuard(string $handlerClass, string $_expectedPermission): void
    {
        $reflectionClass = new ReflectionClass($handlerClass);
        $getAttributes   = $reflectionClass->getAttributes(Get::class);

        self::assertCount(1, $getAttributes);

        /** @var Get $get */
        $get = $getAttributes[0]->newInstance();

        self::assertSame([RequireExistingOrganizationMiddleware::class], $get->middleware);
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function memberViewHandlerClasses(): iterable
    {
        yield 'organization settings' => [OrganizationSettingsHandler::class, 'organizations.view'];

        yield 'organization members' => [OrganizationMembersHandler::class, 'organizations.members.view'];
    }
}
