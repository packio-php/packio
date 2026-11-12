<?php

declare(strict_types=1);

namespace PackioTest\Unit\Access\Config;

use Packio\Access\Infrastructure\Rbac\Rule\OrganizationMemberRule;
use Packio\Access\Infrastructure\Rbac\Rule\OrganizationOwnerRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;

final class AccessConfigTest extends TestCase
{
    /**
     * @param class-string $expectedRule
     */
    #[Test]
    #[DataProvider('organizationScopedUserPermissionRules')]
    public function userOrganizationScopedPermissionsUseExpectedRules(string $permission, string $expectedRule): void
    {
        $config = require dirname(__DIR__, 4) . '/config/autoload/access.global.php';

        self::assertSame(
            $expectedRule,
            $config['access']['roles']['user'][$permission] ?? null,
        );
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function organizationScopedUserPermissionRules(): iterable
    {
        yield 'organizations.view' => ['organizations.view', OrganizationMemberRule::class];

        yield 'tokens.view' => ['tokens.view', OrganizationMemberRule::class];

        yield 'sources.view' => ['sources.view', OrganizationMemberRule::class];

        yield 'organizations.members.view' => ['organizations.members.view', OrganizationMemberRule::class];

        yield 'packages.view' => ['packages.view', OrganizationMemberRule::class];

        yield 'organizations.manage' => ['organizations.manage', OrganizationOwnerRule::class];

        yield 'tokens.manage' => ['tokens.manage', OrganizationOwnerRule::class];

        yield 'sources.manage' => ['sources.manage', OrganizationOwnerRule::class];

        yield 'organizations.members.manage' => ['organizations.members.manage', OrganizationOwnerRule::class];

        yield 'organizations.invitations.manage' => ['organizations.invitations.manage', OrganizationOwnerRule::class];

        yield 'packages.manage' => ['packages.manage', OrganizationOwnerRule::class];

        yield 'webhooks.manage' => ['webhooks.manage', OrganizationOwnerRule::class];
    }

    #[Test]
    public function packagesViewUsesOrganizationMemberRuleForAdminRole(): void
    {
        $config = require dirname(__DIR__, 4) . '/config/autoload/access.global.php';

        self::assertSame(
            OrganizationMemberRule::class,
            $config['access']['roles']['admin']['packages.view'] ?? null,
        );
    }
}
