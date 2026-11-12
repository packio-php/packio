<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

/**
 * Lightweight result DTO for source synchronization,
 * decoupled from any external module's API DTOs.
 */
final readonly class SourceSyncResult
{
    /**
     * @param list<array<array-key, mixed>> $releases
     * @param array<array-key, mixed>       $readmeSync
     * @param array<array-key, mixed>       $retention
     */
    public function __construct(
        public string $packageName,
        public array $releases,
        public bool $created,
        public array $readmeSync = [],
        public array $retention = [],
    ) {}
}
