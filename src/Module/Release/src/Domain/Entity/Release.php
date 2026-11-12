<?php

declare(strict_types=1);

namespace Packio\Release\Domain\Entity;

use Cake\Chronos\Chronos;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

use function array_key_exists;
use function is_array;

class Release implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    /**
     * @param array<string, mixed> $composerData
     * @param array<string, mixed> $sourceData
     * @param array<string, mixed> $distData
     */
    public function __construct(
        private string $organizationId = '',
        private readonly string $packageName = '',
        private readonly string $version = '',
        private string $reference = '',
        private bool $active = true,
        private array $composerData = [],
        private array $sourceData = [],
        private array $distData = [],
        private int $downloadCount = 0,
        private ?Chronos $releasedAt = null,
        private ?string $upstreamReference = null,
        private ?string $referenceType = null,
    ) {}

    /**
     * @param array<string, mixed> $composerData
     * @param array<string, mixed> $sourceData
     * @param array<string, mixed> $distData
     */
    public static function create(
        string $organizationId,
        string $packageName,
        string $version,
        string $reference,
        array $composerData,
        array $sourceData,
        array $distData,
        ?Chronos $releasedAt = null,
        ?string $upstreamReference = null,
        ?string $referenceType = null,
    ): self {
        return new self(
            organizationId: $organizationId,
            packageName: $packageName,
            version: $version,
            reference: $reference,
            composerData: $composerData,
            sourceData: $sourceData,
            distData: $distData,
            releasedAt: $releasedAt,
            upstreamReference: $upstreamReference,
            referenceType: $referenceType,
        );
    }

    /**
     * @param array<string, mixed> $composerData
     * @param array<string, mixed> $sourceData
     * @param array<string, mixed> $distData
     */
    public function updatePayload(
        string $reference,
        array $composerData,
        array $sourceData,
        array $distData,
        ?Chronos $releasedAt = null,
        ?string $upstreamReference = null,
        ?string $referenceType = null,
    ): void {
        if (
            ! array_key_exists('storage', $distData)
            && $this->reference === $reference
            && isset($this->distData['storage'])
            && is_array($this->distData['storage'])
        ) {
            $distData['storage'] = $this->distData['storage'];
        }

        $this->reference         = $reference;
        $this->composerData      = $composerData;
        $this->sourceData        = $sourceData;
        $this->distData          = $distData;
        $this->releasedAt        = $releasedAt ?? $this->releasedAt;
        $this->upstreamReference = $upstreamReference;
        $this->referenceType     = $referenceType;
    }

    /**
     * @param array<string, mixed> $storageData
     */
    public function updateDistStorageMetadata(array $storageData): void
    {
        $this->distData['storage'] = $storageData;
    }

    public function packageName(): string
    {
        return $this->packageName;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function upstreamReference(): ?string
    {
        return $this->upstreamReference;
    }

    public function referenceType(): ?string
    {
        return $this->referenceType;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    /** @return array<string, mixed> */
    public function composerData(): array
    {
        return $this->composerData;
    }

    /** @return array<string, mixed> */
    public function sourceData(): array
    {
        return $this->sourceData;
    }

    /** @return array<string, mixed> */
    public function distData(): array
    {
        return $this->distData;
    }

    public function downloadCount(): int
    {
        return $this->downloadCount;
    }

    public function releasedAt(): ?Chronos
    {
        return $this->releasedAt;
    }
}
