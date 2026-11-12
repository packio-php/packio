<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

final readonly class ValidationFailureRoutePolicy
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $targetAttribute,
        public array $options,
    ) {}
}
