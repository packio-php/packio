<?php

declare(strict_types=1);

namespace Packio\Application\Validation;

/**
 * One explicitly selected validation target for the matched route.
 */
final readonly class ValidationTargetPlanItem
{
    /**
     * @param array<string, mixed> $options resolved failure response policy; contains the
     *                                      canonical default marker when no override exists
     */
    public function __construct(
        public string $targetAttribute,
        public array $options,
    ) {}

    public function usesCanonicalDefaultPolicy(): bool
    {
        return true === ($this->options[MappedRequestValidationPlanResolver::DEFAULT_FAILURE_OPTIONS] ?? null);
    }
}
