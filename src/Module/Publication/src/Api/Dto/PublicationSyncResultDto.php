<?php

declare(strict_types=1);

namespace Packio\Publication\Api\Dto;

final readonly class PublicationSyncResultDto
{
    /**
     * @param list<array<string, mixed>> $releases
     * @param array<string,mixed>        $readmeSync
     * @param array<string,mixed>        $retention
     */
    public function __construct(
        public string $packageName,
        public array $releases,
        public bool $created,
        public array $readmeSync = [],
        public array $retention = [],
    ) {}
}
