<?php

declare(strict_types=1);

namespace Packio\Worker\Api\Dto;

use Cake\Chronos\Chronos;
use Throwable;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function in_array;
use function is_array;
use function is_bool;
use function is_scalar;
use function strtolower;
use function trim;

final readonly class JobFilterCriteria
{
    /** @var list<string> */
    public const array ALLOWED_STATUSES = [
        'pending',
        'running',
        'completed',
        'failed',
        'canceled',
        'held',
        'audited',
    ];

    /**
     * @param list<string> $statuses
     * @param list<string> $types
     */
    public function __construct(
        public ?string $organizationId = null,
        public array $statuses = [],
        public array $types = [],
        public ?string $search = null,
        public ?string $jobId = null,
        public ?string $idempotencyKey = null,
        public ?string $deletionRunId = null,
        public ?string $sourceId = null,
        public ?string $workerId = null,
        public ?Chronos $createdFrom = null,
        public ?Chronos $createdTo = null,
        public ?Chronos $scheduledFrom = null,
        public ?Chronos $scheduledTo = null,
        public ?bool $hasWarnings = null,
        public ?bool $hasError = null,
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public static function fromArray(array $params): self
    {
        return new self(
            organizationId: self::optionalString($params['organization_id'] ?? $params['organizationId'] ?? null),
            statuses: self::normalizedList($params['status'] ?? $params['statuses'] ?? null, self::ALLOWED_STATUSES),
            types: self::normalizedList($params['type'] ?? $params['types'] ?? null),
            search: self::optionalString($params['search'] ?? null),
            jobId: self::optionalString($params['job_id'] ?? $params['jobId'] ?? null),
            idempotencyKey: self::optionalString($params['idempotency_key'] ?? $params['idempotencyKey'] ?? null),
            deletionRunId: self::optionalString($params['deletion_run_id'] ?? $params['deletionRunId'] ?? null),
            sourceId: self::optionalString($params['source_id'] ?? $params['sourceId'] ?? null),
            workerId: self::optionalString($params['worker_id'] ?? $params['workerId'] ?? null),
            createdFrom: self::optionalChronos($params['created_from'] ?? $params['createdFrom'] ?? null),
            createdTo: self::optionalChronos($params['created_to'] ?? $params['createdTo'] ?? null),
            scheduledFrom: self::optionalChronos($params['scheduled_from'] ?? $params['scheduledFrom'] ?? null),
            scheduledTo: self::optionalChronos($params['scheduled_to'] ?? $params['scheduledTo'] ?? null),
            hasWarnings: self::optionalBool($params['has_warnings'] ?? $params['hasWarnings'] ?? null),
            hasError: self::optionalBool($params['has_error'] ?? $params['hasError'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toQueryArray(): array
    {
        return [
            'organization_id' => $this->organizationId ?? '',
            'status'          => $this->statuses,
            'type'            => $this->types,
            'search'          => $this->search ?? '',
            'job_id'          => $this->jobId ?? '',
            'idempotency_key' => $this->idempotencyKey ?? '',
            'deletion_run_id' => $this->deletionRunId ?? '',
            'source_id'       => $this->sourceId ?? '',
            'worker_id'       => $this->workerId ?? '',
            'created_from'    => $this->createdFrom?->format('Y-m-d') ?? '',
            'created_to'      => $this->createdTo?->format('Y-m-d') ?? '',
            'scheduled_from'  => $this->scheduledFrom?->format('Y-m-d') ?? '',
            'scheduled_to'    => $this->scheduledTo?->format('Y-m-d') ?? '',
            'has_warnings'    => true === $this->hasWarnings,
            'has_error'       => true === $this->hasError,
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
                static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
                $values,
            ),
            static fn (string $item): bool => '' !== $item
                && (null === $allowList || in_array($item, $allowList, true)),
        ));

        return array_values(array_unique($normalized));
    }

    private static function optionalChronos(mixed $value): ?Chronos
    {
        $normalized = self::optionalString($value);
        if (null === $normalized) {
            return null;
        }

        try {
            return Chronos::parse($normalized);
        } catch (Throwable) {
            return null;
        }
    }

    private static function optionalBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = self::optionalString($value);
        if (null === $normalized) {
            return null;
        }

        return match (strtolower($normalized)) {
            '1', 'true', 'yes', 'on'  => true,
            '0', 'false', 'no', 'off' => false,
            default                   => null,
        };
    }
}
