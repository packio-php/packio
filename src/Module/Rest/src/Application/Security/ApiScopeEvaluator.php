<?php

declare(strict_types=1);

namespace Packio\Rest\Application\Security;

use function in_array;
use function str_ends_with;
use function str_starts_with;
use function substr;
use function trim;

final readonly class ApiScopeEvaluator
{
    /**
     * @param list<string> $grantedScopes
     */
    public function allows(string $requiredScope, array $grantedScopes): bool
    {
        $requiredScope = trim($requiredScope);
        if ('' === $requiredScope) {
            return true;
        }

        if (in_array('admin', $grantedScopes, true) || in_array('*', $grantedScopes, true)) {
            return true;
        }

        if (in_array($requiredScope, $grantedScopes, true)) {
            return true;
        }

        foreach ($grantedScopes as $grantedScope) {
            if (str_ends_with($grantedScope, ':*') && str_starts_with($requiredScope, substr($grantedScope, 0, -1))) {
                return true;
            }
        }

        if (str_starts_with($requiredScope, 'admin:')) {
            return false;
        }

        if ('read' === $requiredScope) {
            return in_array('write', $grantedScopes, true);
        }

        if ('write' === $requiredScope) {
            return in_array('write', $grantedScopes, true);
        }

        if (str_ends_with($requiredScope, ':read')) {
            return in_array('read', $grantedScopes, true) || in_array('write', $grantedScopes, true);
        }

        if (str_ends_with($requiredScope, ':write')) {
            return in_array('write', $grantedScopes, true);
        }

        return in_array($requiredScope, $grantedScopes, true);
    }
}
