<?php

declare(strict_types=1);

namespace Packio\Access\Application\Service;

use Psr\Container\ContainerInterface;
use Sirix\Mezzio\Rbac\Contract\PermissionsInterface;

final readonly class PermissionsDelegatorFactory
{
    public function __invoke(ContainerInterface $container, string $name, callable $callback): PermissionsInterface
    {
        /** @var PermissionsInterface $permissions */
        $permissions = $callback();

        $registrar = $container->get(AccessRoleRegistrar::class);
        $config    = $container->get('config');

        $roles = (array) ($config['access']['roles'] ?? []);
        $registrar->register($permissions, $roles);

        return $permissions;
    }
}
