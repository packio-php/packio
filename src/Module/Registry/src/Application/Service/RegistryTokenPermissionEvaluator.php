<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Service;

use Sirix\Mezzio\Rbac\PermissionMatcher;

use function trim;

final readonly class RegistryTokenPermissionEvaluator implements TokenPermissionEvaluatorInterface
{
    public function __construct(private PermissionMatcher $permissionMatcher) {}

    public function allows(string $permission, array $permissions): bool
    {
        $permission = trim($permission);
        if ('' === $permission) {
            return false;
        }

        foreach ($permissions as $permissionPattern) {
            $permissionPattern = trim($permissionPattern);
            if ('' === $permissionPattern) {
                continue;
            }

            if ($this->permissionMatcher->matches($permissionPattern, $permission)) {
                return true;
            }
        }

        return false;
    }
}
