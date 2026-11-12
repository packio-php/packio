<?php

declare(strict_types=1);

namespace Packio\Registry\Application\Dto;

final readonly class ReleaseData
{
    /**
     * @param array<string, mixed>  $composerJson
     * @param array<string, mixed>  $distMetadata
     * @param array<string, string> $source
     */
    public function __construct(
        public string $id,
        public string $version,
        public string $reference,
        public bool $active,
        public ?array $composerJson,
        public ?array $distMetadata,
        public array $source = [],
        public ?string $releasedAt = null,
    ) {}
}
