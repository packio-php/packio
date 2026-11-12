<?php

declare(strict_types=1);

use Common\Registry\RegistryProtocolPermissions;
use Packio\Access\Infrastructure\Rbac\Rule\OrganizationMemberRule;
use Packio\Access\Infrastructure\Rbac\Rule\OrganizationOwnerRule;

return [
    'access' => [
        'roles' => [
            'user'  => [
                'dashboard.view',
                'organizations.view'               => OrganizationMemberRule::class,
                'organizations.manage'             => OrganizationOwnerRule::class,
                'packages.view'                    => OrganizationMemberRule::class,
                'tokens.view'                      => OrganizationMemberRule::class,
                'tokens.manage'                    => OrganizationOwnerRule::class,
                'sources.view'                     => OrganizationMemberRule::class,
                'sources.manage'                   => OrganizationOwnerRule::class,
                'organizations.members.view'       => OrganizationMemberRule::class,
                'organizations.members.manage'     => OrganizationOwnerRule::class,
                'organizations.invitations.manage' => OrganizationOwnerRule::class,
                'packages.manage'                  => OrganizationOwnerRule::class,
                'webhooks.manage'                  => OrganizationOwnerRule::class,
                RegistryProtocolPermissions::PACKAGES_READ_PRIVATE,
                'profile.manage',
                'registry.setup.private',
            ],
            'admin' => [
                'admin.*',
                'packages.view' => OrganizationMemberRule::class,
            ],
        ],
    ],
];
