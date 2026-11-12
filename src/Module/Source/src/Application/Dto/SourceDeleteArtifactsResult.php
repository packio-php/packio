<?php

declare(strict_types=1);

namespace Packio\Source\Application\Dto;

final readonly class SourceDeleteArtifactsResult
{
    /**
     * @param list<array{package_name: string, error: string}> $archiveCleanupWarnings
     */
    public function __construct(
        public int $packagesFound,
        public int $packagesDeleted,
        public int $releasesDeleted,
        public int $archiveCleanupScheduled,
        public int $archiveCleanupFailed,
        public int $webhookEventsDeleted,
        public array $archiveCleanupWarnings = [],
    ) {}

    /**
     * @return array{
     *     packages_found: int,
     *     packages_deleted: int,
     *     releases_deleted: int,
     *     archive_cleanup_scheduled: int,
     *     archive_cleanup_failed: int,
     *     webhook_events_deleted: int
     * }
     */
    public function toArray(): array
    {
        return [
            'packages_found'            => $this->packagesFound,
            'packages_deleted'          => $this->packagesDeleted,
            'releases_deleted'          => $this->releasesDeleted,
            'archive_cleanup_scheduled' => $this->archiveCleanupScheduled,
            'archive_cleanup_failed'    => $this->archiveCleanupFailed,
            'webhook_events_deleted'    => $this->webhookEventsDeleted,
        ];
    }
}
