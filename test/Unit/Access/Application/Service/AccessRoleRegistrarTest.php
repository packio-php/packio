<?php

declare(strict_types=1);

namespace PackioTest\Unit\Access\Application\Service;

use Packio\Access\Application\Service\AccessRoleRegistrar;
use Packio\Access\Infrastructure\Rbac\Rule\OrganizationOwnerRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sirix\Mezzio\Rbac\InMemoryPermissionStore;
use Sirix\Mezzio\Rbac\PermissionMatcher;
use Sirix\Mezzio\Rbac\Permissions;

#[CoversClass(AccessRoleRegistrar::class)]
final class AccessRoleRegistrarTest extends TestCase
{
    #[Test]
    public function itRegistersStringPermissionsWithDefaultRule(): void
    {
        $permissions = $this->permissions();

        new AccessRoleRegistrar()->register($permissions, [
            'user' => ['dashboard.view'],
        ]);

        $association = $permissions->bestAssociationForRole('user', 'dashboard.view');

        self::assertNotNull($association);
        self::assertSame('dashboard.view', $association->getPattern());
        self::assertNull($association->getRule());
    }

    #[Test]
    public function itRegistersKeyedPermissionsWithRuleClass(): void
    {
        $permissions = $this->permissions();

        new AccessRoleRegistrar()->register($permissions, [
            'user' => [
                'organizations.manage' => OrganizationOwnerRule::class,
            ],
        ]);

        $association = $permissions->bestAssociationForRole('user', 'organizations.manage');

        self::assertNotNull($association);
        self::assertSame('organizations.manage', $association->getPattern());
        self::assertSame(OrganizationOwnerRule::class, $association->getRule());
    }

    #[Test]
    public function itSkipsInvalidPermissionsAndRules(): void
    {
        $permissions = $this->permissions();

        new AccessRoleRegistrar()->register($permissions, [
            'user' => [
                '',
                'organizations.manage' => '',
                123,
            ],
        ]);

        self::assertNull($permissions->bestAssociationForRole('user', 'organizations.manage'));
    }

    #[Test]
    public function adminWildcardAllowsAdminAccessOnly(): void
    {
        $permissions = $this->permissions();

        new AccessRoleRegistrar()->register($permissions, [
            'admin' => ['admin.*'],
        ]);

        self::assertNotNull($permissions->bestAssociationForRole('admin', 'admin.access'));
        self::assertNull($permissions->bestAssociationForRole('admin', 'organizations.manage'));
    }

    private function permissions(): Permissions
    {
        return new Permissions(new PermissionMatcher(), new InMemoryPermissionStore());
    }
}
