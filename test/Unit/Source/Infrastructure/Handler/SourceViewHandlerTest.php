<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Source\Infrastructure\Handler\SourceDetailsHandler;
use Packio\Source\Infrastructure\Handler\SourcesHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[CoversClass(SourcesHandler::class)]
#[CoversClass(SourceDetailsHandler::class)]
final class SourceViewHandlerTest extends TestCase
{
    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('memberViewHandlerClasses')]
    public function memberViewRoutesUseSourcesViewPermissionWithRequestContext(string $handlerClass): void
    {
        $reflectionClass = new ReflectionClass($handlerClass);
        $canAttributes   = $reflectionClass->getAttributes(Can::class);

        self::assertCount(1, $canAttributes);

        /** @var Can $can */
        $can = $canAttributes[0]->newInstance();

        self::assertSame('sources.view', $can->permission);
        self::assertSame([
            'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
            'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
        ], $can->context);
    }

    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('memberViewHandlerClasses')]
    public function memberViewRoutesKeepOrganizationExistenceGuard(string $handlerClass): void
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
    public static function memberViewHandlerClasses(): iterable
    {
        yield 'sources index' => [SourcesHandler::class];

        yield 'source details' => [SourceDetailsHandler::class];
    }
}
