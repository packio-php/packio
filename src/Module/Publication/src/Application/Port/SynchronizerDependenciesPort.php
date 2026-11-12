<?php

declare(strict_types=1);

namespace Packio\Publication\Application\Port;

/**
 * Unified port interface for all external dependencies needed by source publication synchronizers.
 *
 * Aggregates the required operations for:
 * - Source access (resolve secrets, mark sync status)
 * - Package operations (find, upsert, update readme)
 * - Release publishing
 * - Audit logging
 *
 * This is the contract that the Application layer provides to Infrastructure synchronizers.
 */
interface SynchronizerDependenciesPort
{
    /**
     * Find a source by its ID.
     *
     * @return null|array<string, mixed> source data (camelCase keys), or null if not found
     */
    public function findSource(string $sourceId): ?array;

    /**
     * Resolve the authorization secret (token) for a given source.
     */
    public function resolveSourceAuthorizationSecret(string $sourceId): ?string;

    /**
     * Mark a source sync as succeeded.
     *
     * @return null|array<string, mixed> updated source data, or null if not found
     */
    public function markSyncSucceeded(string $sourceId): ?array;

    /**
     * Mark a source sync as failed.
     *
     * @return null|array<string, mixed> updated source data, or null if not found
     */
    public function markSyncFailed(string $sourceId, string $error): ?array;

    /**
     * Find a package within an organization by name.
     *
     * @return null|array<string, mixed> package data (camelCase keys), or null if not found
     */
    public function findPackageInOrganization(string $organizationId, string $name): ?array;

    /**
     * Upsert (insert or update) a package.
     *
     * @param array<string, mixed> $packageData package data (camelCase keys)
     *
     * @return array<string, mixed> the upserted package data
     */
    public function upsertPackage(array $packageData): array;

    /**
     * Update the README HTML for a given package.
     *
     * @return null|array<string, mixed> updated package data, or null if not found
     */
    public function updatePackageReadmeHtml(string $organizationId, string $name, ?string $readmeHtml): ?array;

    /**
     * Publish a new release.
     *
     * @param array<string, mixed> $releaseData release data (camelCase keys)
     *
     * @return array<string, mixed> the published release data
     */
    public function publishRelease(array $releaseData): array;

    /**
     * Apply source-level release retention for a synchronized package.
     *
     * @return array<string, mixed>
     */
    public function applyReleaseRetention(
        string $organizationId,
        string $sourceId,
        string $packageName,
        int $keepLastReleases
    ): array;

    /**
     * Record an audit event.
     *
     * @param array<string, mixed> $context
     */
    public function recordAudit(
        string $event,
        array $context,
        string $organizationId,
        string $sourceId = '',
        string $packageName = '',
    ): void;
}
