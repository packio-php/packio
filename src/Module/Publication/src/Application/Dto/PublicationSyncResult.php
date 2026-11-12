<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Dto;

/**
 * Application-layer DTO for publication sync results.
 *
 * Uses only scalar types (string, int, bool, array) instead of
 * external Api DTOs to maintain clean module boundaries.
 */
final readonly class PublicationSyncResult
{
    /**
     * @param array<string, mixed>       $source     Source data (camelCase keys)
     * @param array<string, mixed>       $package    Package data (camelCase keys)
     * @param list<array<string, mixed>> $releases   List of release data (camelCase keys each)
     * @param array<string, mixed>       $readmeSync README synchronization step data
     * @param array<string, mixed>       $retention  Release retention step data
     */
    public function __construct(
        public array $source,
        public array $package,
        public array $releases,
        public bool $created,
        public array $readmeSync = [],
        public array $retention = [],
    ) {}
}
