<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Identity\Infrastructure\Handler\RegistryTokensHandler;
use Packio\Registry\Infrastructure\Handler\RegistrySetupHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[CoversClass(RegistrySetupHandler::class)]
#[CoversClass(RegistryTokensHandler::class)]
final class RegistryViewHandlerTest extends TestCase
{
    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('viewHandlerClasses')]
    public function viewRoutesUseExpectedPermissionWithRequestContext(string $handlerClass, string $expectedPermission): void
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
    #[DataProvider('viewHandlerClasses')]
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
    public static function viewHandlerClasses(): iterable
    {
        yield 'registry setup' => [RegistrySetupHandler::class, 'organizations.view'];

        yield 'registry tokens' => [RegistryTokensHandler::class, 'tokens.view'];
    }
}
