<?php

declare(strict_types=1);

namespace PackioTest\Unit\Organization\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Infrastructure\Handler\OrganizationInvitationCreateHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationInvitationDeleteHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationInvitationRevokeHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationMemberRemoveHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationMemberRoleUpdateHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationSettingsDeleteHandler;
use Packio\Organization\Infrastructure\Handler\OrganizationSettingsUpdateHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[CoversClass(OrganizationInvitationCreateHandler::class)]
#[CoversClass(OrganizationInvitationDeleteHandler::class)]
#[CoversClass(OrganizationInvitationRevokeHandler::class)]
#[CoversClass(OrganizationMemberRemoveHandler::class)]
#[CoversClass(OrganizationMemberRoleUpdateHandler::class)]
#[CoversClass(OrganizationSettingsDeleteHandler::class)]
#[CoversClass(OrganizationSettingsUpdateHandler::class)]
final class OrganizationSettingsUpdateHandlerTest extends TestCase
{
    /** @param class-string $handlerClass */
    #[Test]
    #[DataProvider('ownerOnlyHandlerClasses')]
    public function ownerOnlyRoutesUseExpectedPermissionWithRequestContext(string $handlerClass, string $expectedPermission): void
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
    #[DataProvider('ownerOnlyHandlerClasses')]
    public function ownerOnlyRoutesUseRbacWithoutLegacyManageGuard(string $handlerClass, string $_expectedPermission): void
    {
        $reflectionClass = new ReflectionClass($handlerClass);
        $postAttributes  = $reflectionClass->getAttributes(Post::class);

        self::assertCount(1, $postAttributes);

        /** @var Post $post */
        $post = $postAttributes[0]->newInstance();

        self::assertNull($post->middleware);
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function ownerOnlyHandlerClasses(): iterable
    {
        yield 'organization invitation create' => [OrganizationInvitationCreateHandler::class, 'organizations.invitations.manage'];

        yield 'organization invitation delete' => [OrganizationInvitationDeleteHandler::class, 'organizations.invitations.manage'];

        yield 'organization invitation revoke' => [OrganizationInvitationRevokeHandler::class, 'organizations.invitations.manage'];

        yield 'organization member remove' => [OrganizationMemberRemoveHandler::class, 'organizations.members.manage'];

        yield 'organization member role update' => [OrganizationMemberRoleUpdateHandler::class, 'organizations.members.manage'];

        yield 'organization settings delete' => [OrganizationSettingsDeleteHandler::class, 'organizations.manage'];

        yield 'organization settings update' => [OrganizationSettingsUpdateHandler::class, 'organizations.manage'];
    }
}
