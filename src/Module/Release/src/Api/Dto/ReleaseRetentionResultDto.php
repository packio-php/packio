<?php

declare(strict_types=1);

namespace Packio\Release\Api\Dto;

final readonly class ReleaseRetentionResultDto
{
    /**
     * @param list<string> $keptVersions
     * @param list<string> $deactivatedVersions
     * @param list<string> $ignoredDevVersions
     */
    public function __construct(
        public int $limit,
        public int $keptCount,
        public int $deactivatedCount,
        public int $ignoredDevCount,
        public array $keptVersions = [],
        public array $deactivatedVersions = [],
        public array $ignoredDevVersions = [],
    ) {}

    /**
     * @return array{
     *     limit: int,
     *     kept_count: int,
     *     deactivated_count: int,
     *     ignored_dev_count: int,
     *     kept_versions: list<string>,
     *     deactivated_versions: list<string>,
     *     ignored_dev_versions: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'limit'                => $this->limit,
            'kept_count'           => $this->keptCount,
            'deactivated_count'    => $this->deactivatedCount,
            'ignored_dev_count'    => $this->ignoredDevCount,
            'kept_versions'        => $this->keptVersions,
            'deactivated_versions' => $this->deactivatedVersions,
            'ignored_dev_versions' => $this->ignoredDevVersions,
        ];
    }
}
