<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

use RuntimeException;

final readonly class SourceDto
{
    public function __construct(
        public string $id,
        public string $provider,
        public string $type,
        public string $displayName,
        public string $canonicalUrl,
        public string $url,
        public string $owner,
        public string $repository,
        public string $defaultRef,
        public string $organizationId,
        public string $visibility = 'public',
        public bool $active = true,
        public string $lastSyncStatus = 'pending',
        public string $lastSuccessfulSyncAt = '',
        public string $lastFailedSyncAt = '',
        public string $lastSyncError = '',
        public bool $archived = false,
        public string $archivedAt = '',
        public string $status = 'active',
        public string $createdAt = '',
        public string $providerAuthorizationId = '',
        public string $providerResourceId = '',
        /** @var array<string, mixed> */
        public array $providerConfig = [],
        public string $authType = '',
        public bool $credentialsConfigured = false,
        public string $authorizationStatus = '',
        public int $keepLastReleases = 0,
        public string $updatedAt = '',
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        if (! isset($payload['organization_id'])) {
            throw new RuntimeException('organization_id is required.');
        }

        return new self(
            (string) ($payload['id'] ?? ''),
            (string) ($payload['provider'] ?? $payload['type'] ?? ''),
            (string) ($payload['type'] ?? ''),
            (string) ($payload['display_name'] ?? ''),
            (string) ($payload['canonical_url'] ?? $payload['url'] ?? ''),
            (string) ($payload['url'] ?? ''),
            (string) ($payload['owner'] ?? ''),
            (string) ($payload['repository'] ?? ''),
            (string) ($payload['default_ref'] ?? ''),
            (string) $payload['organization_id'],
            (string) ($payload['visibility'] ?? 'public'),
            (bool) ($payload['active'] ?? true),
            (string) ($payload['last_sync_status'] ?? 'pending'),
            (string) ($payload['last_successful_sync_at'] ?? ''),
            (string) ($payload['last_failed_sync_at'] ?? ''),
            (string) ($payload['last_sync_error'] ?? ''),
            (bool) ($payload['archived'] ?? false),
            (string) ($payload['archived_at'] ?? ''),
            (string) ($payload['status'] ?? 'active'),
            (string) ($payload['created_at'] ?? ''),
            (string) ($payload['provider_authorization_id'] ?? ''),
            (string) ($payload['provider_resource_id'] ?? ''),
            (array) ($payload['provider_config'] ?? []),
            (string) ($payload['auth_type'] ?? ''),
            (bool) ($payload['credentials_configured'] ?? false),
            (string) ($payload['authorization_status'] ?? ''),
            (int) ($payload['keep_last_releases'] ?? 0),
            (string) ($payload['updated_at'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                        => $this->id,
            'provider'                  => $this->provider,
            'type'                      => $this->type,
            'display_name'              => $this->displayName,
            'canonical_url'             => $this->canonicalUrl,
            'url'                       => $this->url,
            'owner'                     => $this->owner,
            'repository'                => $this->repository,
            'default_ref'               => $this->defaultRef,
            'visibility'                => $this->visibility,
            'active'                    => $this->active ? '1' : '0',
            'last_sync_status'          => $this->lastSyncStatus,
            'last_successful_sync_at'   => $this->lastSuccessfulSyncAt,
            'last_failed_sync_at'       => $this->lastFailedSyncAt,
            'last_sync_error'           => $this->lastSyncError,
            'archived'                  => $this->archived ? '1' : '0',
            'archived_at'               => $this->archivedAt,
            'status'                    => $this->status,
            'created_at'                => $this->createdAt,
            'organization_id'           => $this->organizationId,
            'provider_authorization_id' => $this->providerAuthorizationId,
            'provider_resource_id'      => $this->providerResourceId,
            'provider_config'           => $this->providerConfig,
            'auth_type'                 => $this->authType,
            'credentials_configured'    => $this->credentialsConfigured
                ? '1'
                : '0',
            'authorization_status'      => $this->authorizationStatus,
            'keep_last_releases'        => $this->keepLastReleases,
            'updated_at'                => $this->updatedAt,
        ];
    }
}
