<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Dto;

final readonly class ProviderDescriptor
{
    /**
     * @param array<string> $authorizationStrategies
     */
    public function __construct(
        public string $name,
        public string $label,
        public bool $supportsAuthorization,
        public bool $supportsSynchronization = true,
        public array $authorizationStrategies = [],
    ) {}
}
