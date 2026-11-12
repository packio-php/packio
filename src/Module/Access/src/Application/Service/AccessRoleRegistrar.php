<?php

declare(strict_types=1);

namespace Packio\Access\Application\Service;

use InvalidArgumentException;
use Sirix\Mezzio\Rbac\Contract\PermissionsInterface;
use Sirix\Mezzio\Rbac\Contract\RuleInterface;

use function is_int;
use function is_string;
use function trim;

final class AccessRoleRegistrar
{
    /**
     * @param array<mixed> $roles
     */
    public function register(PermissionsInterface $permissions, array $roles): void
    {
        foreach ($roles as $role => $mappedPermissions) {
            if (! is_string($role)) {
                continue;
            }

            if ('' === $role) {
                continue;
            }

            try {
                $permissions->addRole($role);
            } catch (InvalidArgumentException) {
            }

            foreach ((array) $mappedPermissions as $permission => $rule) {
                if (is_int($permission)) {
                    $permission = $rule;
                    $rule       = null;
                }

                if (! is_string($permission)) {
                    continue;
                }

                if ('' === trim($permission)) {
                    continue;
                }

                if (! $this->isRule($rule)) {
                    continue;
                }

                $permissions->associate($role, $permission, $rule);
            }
        }
    }

    private function isRule(mixed $rule): bool
    {
        if (null === $rule) {
            return true;
        }

        if ($rule instanceof RuleInterface) {
            return true;
        }

        return is_string($rule) && '' !== trim($rule);
    }
}
