<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Identity\Infrastructure\Handler\RegistryTokenIssueHandler;
use Packio\Identity\Infrastructure\Handler\RegistryTokenRevokeHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[CoversClass(RegistryTokenIssueHandler::class)]
#[CoversClass(RegistryTokenRevokeHandler::class)]
final class RegistryTokenHandlerTest extends TestCase
{
    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('ownerOnlyHandlerClasses')]
    public function ownerOnlyRoutesUseTokensManagePermissionWithRequestContext(string $handlerClass): void
    {
        $reflectionClass = new ReflectionClass($handlerClass);
        $canAttributes   = $reflectionClass->getAttributes(Can::class);

        self::assertCount(1, $canAttributes);

        /** @var Can $can */
        $can = $canAttributes[0]->newInstance();

        self::assertSame('tokens.manage', $can->permission);
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
        yield 'registry token issue' => [RegistryTokenIssueHandler::class];

        yield 'registry token revoke' => [RegistryTokenRevokeHandler::class];
    }
}
