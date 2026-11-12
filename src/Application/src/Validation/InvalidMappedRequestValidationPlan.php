<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

use RuntimeException;

use function sprintf;

final class InvalidMappedRequestValidationPlan extends RuntimeException
{
    public static function missingTarget(string $targetAttribute): self
    {
        return new self(sprintf(
            'Mapped request attribute "%s" is missing or not an object after the mapping stage.',
            $targetAttribute,
        ));
    }
}
