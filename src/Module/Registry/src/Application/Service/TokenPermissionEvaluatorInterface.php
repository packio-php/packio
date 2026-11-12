<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Service;

interface TokenPermissionEvaluatorInterface
{
    /**
     * @param list<string> $permissions
     */
    public function allows(string $permission, array $permissions): bool;
}
