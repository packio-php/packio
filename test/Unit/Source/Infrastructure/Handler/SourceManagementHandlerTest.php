<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Source\Infrastructure\Handler\SourceArchiveHandler;
use Packio\Source\Infrastructure\Handler\SourceDeleteHandler;
use Packio\Source\Infrastructure\Handler\SourceSettingsUpdateHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[CoversClass(SourceArchiveHandler::class)]
#[CoversClass(SourceDeleteHandler::class)]
#[CoversClass(SourceSettingsUpdateHandler::class)]
final class SourceManagementHandlerTest extends TestCase
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

    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('ownerOnlyHandlerClasses')]
    public function ownerOnlyRoutesUseRbacWithoutLegacyManageGuard(string $handlerClass): void
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
    public static function ownerOnlyHandlerClasses(): iterable
    {
        yield 'source archive' => [SourceArchiveHandler::class];

        yield 'source delete' => [SourceDeleteHandler::class];

        yield 'source settings update' => [SourceSettingsUpdateHandler::class];
    }
}
