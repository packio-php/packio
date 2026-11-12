<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use function preg_replace;
use function strtolower;

final class ValidationErrorKeyNormalizer
{
    public function normalize(string $property): string
    {
        $normalized = (string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property);

        return strtolower($normalized);
    }
}
