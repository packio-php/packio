<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationCheckHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationDeleteHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationOAuthStoreHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationResourcesHandler;
use Packio\Source\Infrastructure\Handler\SourceAuthorizationStoreHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[CoversClass(SourceAuthorizationStoreHandler::class)]
#[CoversClass(SourceAuthorizationOAuthStoreHandler::class)]
#[CoversClass(SourceAuthorizationDeleteHandler::class)]
#[CoversClass(SourceAuthorizationCheckHandler::class)]
#[CoversClass(SourceAuthorizationResourcesHandler::class)]
final class SourceAuthorizationRouteHandlerTest extends TestCase
{
    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('ownerOnlyHandlerClasses')]
    public function ownerOnlyRoutesUseSourcesManagePermissionWithRequestContext(string $handlerClass): void
    {
        $reflectionClass = new ReflectionClass($handlerClass);
        $canAttributes   = $reflectionClass->getAttributes(Can::class);

        self::assertCount(1, $canAttributes);

        /** @var Can $can */
        $can = $canAttributes[0]->newInstance();

        self::assertSame('sources.manage', $can->permission);
        self::assertSame([
            'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
            'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
        ], $can->context);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function ownerOnlyHandlerClasses(): iterable
    {
        yield 'source authorization store' => [SourceAuthorizationStoreHandler::class];

        yield 'source authorization oauth store' => [SourceAuthorizationOAuthStoreHandler::class];

        yield 'source authorization delete' => [SourceAuthorizationDeleteHandler::class];

        yield 'source authorization check' => [SourceAuthorizationCheckHandler::class];

        yield 'source authorization resources' => [SourceAuthorizationResourcesHandler::class];
    }

    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('postHandlerClasses')]
    public function postRoutesUseRbacWithoutLegacyManageGuard(string $handlerClass): void
    {
        $reflectionClass = new ReflectionClass($handlerClass);
        $postAttributes  = $reflectionClass->getAttributes(Post::class);

        self::assertCount(1, $postAttributes);

        /** @var Post $post */
        $post = $postAttributes[0]->newInstance();

        self::assertNull($post->middleware);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function postHandlerClasses(): iterable
    {
        yield 'source authorization store' => [SourceAuthorizationStoreHandler::class];

        yield 'source authorization oauth store' => [SourceAuthorizationOAuthStoreHandler::class];

        yield 'source authorization delete' => [SourceAuthorizationDeleteHandler::class];

        yield 'source authorization check' => [SourceAuthorizationCheckHandler::class];
    }

    #[Test]
    public function getRoutesUseRbacWithoutLegacyManageGuard(): void
    {
        $reflectionClass = new ReflectionClass(SourceAuthorizationResourcesHandler::class);
        $getAttributes   = $reflectionClass->getAttributes(Get::class);

        self::assertCount(1, $getAttributes);

        /** @var Get $get */
        $get = $getAttributes[0]->newInstance();

        self::assertNull($get->middleware);
    }
}
