<?php

declare(strict_types=1);

namespace Packio\Package\Domain\Entity;

use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class Package implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    public function __construct(
        private string $organizationId = '',
        private readonly string $name = '',
        private string $type = 'library',
        private string $description = '',
        private string $visibility = 'public',
        private bool $active = true,
        private string $sourceId = '',
        private string $sourceType = '',
        private string $sourceUrl = '',
        private ?string $readmeHtml = null,
        private int $downloadCount = 0,
    ) {}

    public static function create(
        string $organizationId,
        string $name,
        string $type,
        string $description,
        string $visibility,
        string $sourceId,
        string $sourceType,
        string $sourceUrl,
        ?string $readmeHtml = null,
        bool $active = true
    ): self {
        return new self(organizationId: $organizationId, name: $name, type: $type, description: $description, visibility: $visibility, active: $active, sourceId: $sourceId, sourceType: $sourceType, sourceUrl: $sourceUrl, readmeHtml: $readmeHtml);
    }

    public function updateMetadata(
        string $type,
        string $description,
        string $visibility,
        string $sourceId,
        string $sourceType,
        string $sourceUrl
    ): void {
        $this->type        = $type;
        $this->description = $description;
        $this->visibility  = $visibility;
        $this->sourceId    = $sourceId;
        $this->sourceType  = $sourceType;
        $this->sourceUrl   = $sourceUrl;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function visibility(): string
    {
        return $this->visibility;
    }

    public function sourceType(): string
    {
        return $this->sourceType;
    }

    public function sourceId(): string
    {
        return $this->sourceId;
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

    public function sourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function readmeHtml(): ?string
    {
        return $this->readmeHtml;
    }

    public function updateReadmeHtml(?string $readmeHtml): void
    {
        $this->readmeHtml = $readmeHtml;
    }

    public function downloadCount(): int
    {
        return $this->downloadCount;
    }
}
