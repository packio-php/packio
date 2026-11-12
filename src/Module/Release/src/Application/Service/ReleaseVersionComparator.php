<?php

declare(strict_types=1);

namespace Packio\Release\Application\Service;

use function str_starts_with;
use function strcmp;
use function version_compare;

final readonly class ReleaseVersionComparator
{
    public function compare(string $left, string $right): int
    {
        $leftIsDev  = str_starts_with($left, 'dev-');
        $rightIsDev = str_starts_with($right, 'dev-');

        if ($leftIsDev && ! $rightIsDev) {
            return 1;
        }

        if (! $leftIsDev && $rightIsDev) {
            return -1;
        }

        if ($leftIsDev) {
            return strcmp($left, $right);
        }

        return version_compare($right, $left);
    }
}
