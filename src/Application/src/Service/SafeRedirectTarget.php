<?php

declare(strict_types=1);

namespace Packio\Application\Service;

use function is_string;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function trim;

final readonly class SafeRedirectTarget
{
    public function normalize(mixed $target): string
    {
        if (! is_string($target)) {
            return '';
        }

        $target = trim($target);
        if ('' === $target) {
            return '';
        }

        if (! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return '';
        }

        if (str_contains($target, '\\')) {
            return '';
        }

        if (1 === preg_match('/[\x00-\x1F\x7F]/', $target)) {
            return '';
        }

        return $target;
    }
}
