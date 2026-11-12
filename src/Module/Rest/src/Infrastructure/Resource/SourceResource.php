<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Source\Api\Dto\SourceDto;

final readonly class SourceResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(SourceDto $sourceDto): array
    {
        return [
            'id'                        => $sourceDto->id,
            'provider'                  => $sourceDto->provider,
            'type'                      => $sourceDto->type,
            'display_name'              => $sourceDto->displayName,
            'canonical_url'             => $sourceDto->canonicalUrl,
            'url'                       => $sourceDto->url,
            'owner'                     => $sourceDto->owner,
            'repository'                => $sourceDto->repository,
            'default_ref'               => $sourceDto->defaultRef,
            'organization_id'           => $sourceDto->organizationId,
            'visibility'                => $sourceDto->visibility,
            'active'                    => $sourceDto->active,
            'last_sync_status'          => $sourceDto->lastSyncStatus,
            'last_successful_sync_at'   => '' === $sourceDto->lastSuccessfulSyncAt ? null : $sourceDto->lastSuccessfulSyncAt,
            'last_failed_sync_at'       => '' === $sourceDto->lastFailedSyncAt ? null : $sourceDto->lastFailedSyncAt,
            'last_sync_error'           => '' === $sourceDto->lastSyncError ? null : $sourceDto->lastSyncError,
            'archived'                  => $sourceDto->archived,
            'archived_at'               => '' === $sourceDto->archivedAt ? null : $sourceDto->archivedAt,
            'status'                    => $sourceDto->status,
            'created_at'                => '' === $sourceDto->createdAt ? null : $sourceDto->createdAt,
            'updated_at'                => '' === $sourceDto->updatedAt ? null : $sourceDto->updatedAt,
            'provider_authorization_id' => '' === $sourceDto->providerAuthorizationId ? null : $sourceDto->providerAuthorizationId,
            'provider_resource_id'      => '' === $sourceDto->providerResourceId ? null : $sourceDto->providerResourceId,
            'provider_config'           => $sourceDto->providerConfig,
            'auth_type'                 => '' === $sourceDto->authType ? null : $sourceDto->authType,
            'credentials_configured'    => $sourceDto->credentialsConfigured,
            'authorization_status'      => '' === $sourceDto->authorizationStatus ? null : $sourceDto->authorizationStatus,
            'keep_last_releases'        => $sourceDto->keepLastReleases,
        ];
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @return array<string, mixed>
     */
    public static function withoutWebhookSecret(array $resource): array
    {
        unset($resource['provider_config']['webhook_secret']);

        return $resource;
    }
}
