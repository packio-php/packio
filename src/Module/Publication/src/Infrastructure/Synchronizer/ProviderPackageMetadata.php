<?php

declare(strict_types=1);

namespace Packio\Publication\Infrastructure\Synchronizer;

final readonly class ProviderPackageMetadata
{
    /**
     * @param array<string, mixed> $composer
     */
    public function __construct(
        public object $nativeMetadata,
        public array $composer,
        public string $refName,
        public string $defaultBranch,
        public string $commitReference,
        public string $refType,
    ) {}
}
