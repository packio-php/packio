<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Page;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function in_array;
use function is_array;
use function is_scalar;
use function strtolower;
use function trim;

final readonly class PackageIndexFilterCriteria
{
    /** @var list<string> */
    public const array ALLOWED_VISIBILITIES = [
        'public',
        'private',
    ];

    /** @var list<string> */
    public const array ALLOWED_LISTED = [
        'listed',
        'hidden',
    ];

    /** @var list<string> */
    public const array ALLOWED_SOURCE = [
        'managed',
        'manual',
    ];

    /** @var list<string> */
    public const array ALLOWED_SYNC_STATUSES = [
        'pending',
        'success',
        'failed',
    ];

    /**
     * @param list<string> $visibilities
     * @param list<string> $sourceSyncStatuses
     */
    public function __construct(
        public ?string $search = null,
        public array $visibilities = [],
        public ?string $listed = null,
        public ?string $source = null,
        public array $sourceSyncStatuses = [],
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public static function fromArray(array $params): self
    {
        return new self(
            search: self::optionalString($params['search'] ?? null),
            visibilities: self::normalizedList(
                $params['visibility'] ?? $params['visibilities'] ?? null,
                self::ALLOWED_VISIBILITIES,
            ),
            listed: self::allowedString($params['listed'] ?? null, self::ALLOWED_LISTED),
            source: self::allowedString($params['source'] ?? null, self::ALLOWED_SOURCE),
            sourceSyncStatuses: self::normalizedList(
                $params['source_sync_status'] ?? $params['sourceSyncStatuses'] ?? null,
                self::ALLOWED_SYNC_STATUSES,
            ),
        );
    }

    /**
     * @return array{search: string, visibility: list<string>, listed: string, source: string, source_sync_status: list<string>}
     */
    public function toQueryArray(): array
    {
        return [
            'search'             => $this->search ?? '',
            'visibility'         => $this->visibilities,
            'listed'             => $this->listed ?? '',
            'source'             => $this->source ?? '',
            'source_sync_status' => $this->sourceSyncStatuses,
        ];
    }

    private static function optionalString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return '' === $normalized ? null : $normalized;
    }

    /**
     * @param list<string> $allowList
     */
    private static function allowedString(mixed $value, array $allowList): ?string
    {
        $normalized = self::optionalString($value);
        if (null === $normalized) {
            return null;
        }

        $normalized = strtolower($normalized);

        return in_array($normalized, $allowList, true) ? $normalized : null;
    }

    /**
     * @param null|list<string> $allowList
     *
     * @return list<string>
     */
    private static function normalizedList(mixed $value, ?array $allowList = null): array
    {
        $values = is_array($value) ? $value : explode(',', (string) ($value ?? ''));

        $normalized = array_values(array_filter(
            array_map(
                static fn (mixed $item): string => is_scalar($item) ? strtolower(trim((string) $item)) : '',
                $values,
            ),
            static fn (string $item): bool => '' !== $item
                && (null === $allowList || in_array($item, $allowList, true)),
        ));

        return array_values(array_unique($normalized));
    }
}
