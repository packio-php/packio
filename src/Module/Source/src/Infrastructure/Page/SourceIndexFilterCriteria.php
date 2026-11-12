<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Page;

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

final readonly class SourceIndexFilterCriteria
{
    /** @var list<string> */
    public const array ALLOWED_VISIBILITIES = [
        'public',
        'private',
    ];

    /** @var list<string> */
    public const array ALLOWED_STATUSES = [
        'active',
        'inactive',
        'archived',
    ];

    /** @var list<string> */
    public const array ALLOWED_SYNC_STATUSES = [
        'pending',
        'success',
        'failed',
    ];

    /**
     * @param list<string> $providers
     * @param list<string> $visibilities
     * @param list<string> $statuses
     * @param list<string> $syncStatuses
     */
    public function __construct(
        public ?string $search = null,
        public array $providers = [],
        public array $visibilities = [],
        public array $statuses = [],
        public array $syncStatuses = [],
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public static function fromArray(array $params): self
    {
        return new self(
            search: self::optionalString($params['search'] ?? null),
            providers: self::normalizedList($params['provider'] ?? $params['providers'] ?? null),
            visibilities: self::normalizedList(
                $params['visibility'] ?? $params['visibilities'] ?? null,
                self::ALLOWED_VISIBILITIES,
            ),
            statuses: self::normalizedList(
                $params['status'] ?? $params['statuses'] ?? null,
                self::ALLOWED_STATUSES,
            ),
            syncStatuses: self::normalizedList(
                $params['sync_status'] ?? $params['syncStatuses'] ?? null,
                self::ALLOWED_SYNC_STATUSES,
            ),
        );
    }

    /**
     * @return array{search: string, provider: list<string>, visibility: list<string>, status: list<string>, sync_status: list<string>}
     */
    public function toQueryArray(): array
    {
        return [
            'search'      => $this->search ?? '',
            'provider'    => $this->providers,
            'visibility'  => $this->visibilities,
            'status'      => $this->statuses,
            'sync_status' => $this->syncStatuses,
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
