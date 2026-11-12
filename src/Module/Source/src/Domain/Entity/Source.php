<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Entity;

use Cake\Chronos\Chronos;
use InvalidArgumentException;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class Source implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_DELETING = 'deleting';

    /**
     * @param array<string, mixed> $providerConfig
     */
    public function __construct(
        private string $organizationId = '',
        private readonly string $provider = '',
        private readonly string $canonicalUrl = '',
        private readonly string $displayName = '',
        private string $providerAuthorizationId = '',
        private string $providerResourceId = '',
        private array $providerConfig = [],
        private string $defaultRef = 'main',
        private string $visibility = 'public',
        private bool $active = true,
        private string $lastSyncStatus = 'pending',
        private ?Chronos $lastSuccessfulSyncAt = null,
        private ?Chronos $lastFailedSyncAt = null,
        private string $lastSyncError = '',
        private ?Chronos $archivedAt = null,
        private string $status = self::STATUS_ACTIVE,
        private ?string $deletionRunId = null,
        private ?string $providerInstanceId = null,
        private int $keepLastReleases = 0,
    ) {
        $this->status = self::STATUS_DELETING === $status ? self::STATUS_DELETING : self::STATUS_ACTIVE;
        $this->active = self::STATUS_ACTIVE === $this->status && $active;
        $this->updateKeepLastReleases($keepLastReleases);
    }

    /**
     * @param array<string, mixed> $providerConfig
     */
    public static function register(
        string $organizationId,
        string $provider,
        string $canonicalUrl,
        string $displayName,
        string $defaultRef,
        string $visibility = 'public',
        ?string $providerAuthorizationId = null,
        ?string $providerResourceId = null,
        array $providerConfig = [],
    ): self {
        return new self(
            organizationId: $organizationId,
            provider: $provider,
            canonicalUrl: $canonicalUrl,
            displayName: $displayName,
            providerAuthorizationId: $providerAuthorizationId ?? '',
            providerResourceId: $providerResourceId ?? '',
            providerConfig: $providerConfig,
            defaultRef: $defaultRef,
            visibility: $visibility,
        );
    }

    public function type(): string
    {
        return $this->provider;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function url(): string
    {
        return $this->canonicalUrl;
    }

    public function canonicalUrl(): string
    {
        return $this->canonicalUrl;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function owner(): string
    {
        return (string) ($this->providerConfig['owner'] ?? '');
    }

    public function repository(): string
    {
        return (string) ($this->providerConfig['repository'] ?? '');
    }

    public function providerAuthorizationId(): string
    {
        return $this->providerAuthorizationId;
    }

    public function providerResourceId(): string
    {
        return $this->providerResourceId;
    }

    public function providerInstanceId(): ?string
    {
        return $this->providerInstanceId;
    }

    public function assignProviderAuthorization(?string $providerAuthorizationId): void
    {
        $this->providerAuthorizationId = $providerAuthorizationId ?? '';
    }

    public function assignProviderInstance(?string $providerInstanceId): void
    {
        $this->providerInstanceId = $providerInstanceId;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerConfig(): array
    {
        return $this->providerConfig;
    }

    /**
     * @param array<string, mixed> $providerConfig
     */
    public function replaceProviderConfig(array $providerConfig): void
    {
        $this->providerConfig = $providerConfig;
    }

    public function defaultRef(): string
    {
        return $this->defaultRef;
    }

    public function updateDefaultRef(string $defaultRef): void
    {
        $this->defaultRef = '' !== $defaultRef ? $defaultRef : $this->defaultRef;
    }

    public function visibility(): string
    {
        return $this->visibility;
    }

    public function keepLastReleases(): int
    {
        return $this->keepLastReleases;
    }

    public function isActive(): bool
    {
        return self::STATUS_ACTIVE === $this->status && $this->active;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function deletionRunId(): ?string
    {
        return $this->deletionRunId;
    }

    public function isDeleting(): bool
    {
        return self::STATUS_DELETING === $this->status;
    }

    public function updateVisibility(string $visibility): void
    {
        $this->visibility = 'private' === $visibility ? 'private' : 'public';
    }

    public function updateKeepLastReleases(int $keepLastReleases): void
    {
        if ($keepLastReleases < 0) {
            throw new InvalidArgumentException('Keep last releases must be zero or greater.');
        }

        $this->keepLastReleases = $keepLastReleases;
    }

    public function activate(): void
    {
        $this->active = true;
        $this->status = self::STATUS_ACTIVE;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt instanceof Chronos;
    }

    public function archivedAt(): ?Chronos
    {
        return $this->archivedAt;
    }

    public function archive(): void
    {
        $this->active     = false;
        $this->status     = self::STATUS_ACTIVE;
        $this->archivedAt = new Chronos();
    }

    public function restore(): void
    {
        $this->active     = true;
        $this->status     = self::STATUS_ACTIVE;
        $this->archivedAt = null;
    }

    public function markDeleting(): void
    {
        $this->active = false;
        $this->status = self::STATUS_DELETING;
    }

    public function cancelDeletingAsInactive(): void
    {
        $this->active = false;
        $this->status = self::STATUS_ACTIVE;
    }

    public function markSyncSucceeded(): void
    {
        $this->lastSyncStatus       = 'success';
        $this->lastSuccessfulSyncAt = new Chronos();
        $this->lastSyncError        = '';
    }

    public function markSyncFailed(string $error): void
    {
        $this->lastSyncStatus   = 'failed';
        $this->lastFailedSyncAt = new Chronos();
        $this->lastSyncError    = $error;
    }

    public function lastSyncStatus(): string
    {
        return $this->lastSyncStatus;
    }

    public function lastSuccessfulSyncAt(): ?Chronos
    {
        return $this->lastSuccessfulSyncAt;
    }

    public function lastFailedSyncAt(): ?Chronos
    {
        return $this->lastFailedSyncAt;
    }

    public function lastSyncError(): string
    {
        return $this->lastSyncError;
    }
}
