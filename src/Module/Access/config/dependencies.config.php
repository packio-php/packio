<?php

declare(strict_types=1);

use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Packio\Access\Application\Service\AccessRoleRegistrar;
use Packio\Access\Application\Service\PermissionsDelegatorFactory;
use Packio\Access\Infrastructure\Rbac\Rule\OrganizationExistsRule;
use Packio\Access\Infrastructure\Rbac\Rule\OrganizationMemberRule;
use Packio\Access\Infrastructure\Rbac\Rule\OrganizationOwnerRule;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Sirix\Mezzio\Rbac\Contract\PermissionsInterface;
use Sirix\Mezzio\Rbac\Contract\PermissionStoreInterface;
use Sirix\Mezzio\Rbac\InMemoryPermissionStore;

return [
    'dependencies'               => [
        'aliases'    => [
            PermissionStoreInterface::class => InMemoryPermissionStore::class,
        ],
        'invokables' => [
            AccessRoleRegistrar::class    => AccessRoleRegistrar::class,
            OrganizationExistsRule::class => OrganizationExistsRule::class,
            OrganizationMemberRule::class => OrganizationMemberRule::class,
        ],
        'factories'  => [
            OrganizationOwnerRule::class => ConfigAbstractFactory::class,
        ],
        'delegators' => [
            PermissionsInterface::class => [
                PermissionsDelegatorFactory::class,
            ],
        ],
    ],
    ConfigAbstractFactory::class => [
        OrganizationOwnerRule::class => [
            OrganizationPolicy::class,
        ],
    ],
];
