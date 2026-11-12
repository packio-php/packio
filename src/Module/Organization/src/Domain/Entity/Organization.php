<?php

declare(strict_types=1);

namespace Packio\Organization\Domain\Entity;

use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

use function preg_replace;
use function strtolower;
use function trim;

class Organization implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_DELETING = 'deleting';

    public function __construct(
        private string $slug = '',
        private string $name = '',
        private bool $anonymousAccess = false,
        private bool $active = true,
        private string $status = self::STATUS_ACTIVE,
        private ?string $deletionRunId = null,
    ) {
        $this->slug   = self::normalizeSlug($slug);
        $this->name   = self::normalizeName($name);
        $this->status = self::STATUS_DELETING === $status ? self::STATUS_DELETING : self::STATUS_ACTIVE;
        $this->active = self::STATUS_ACTIVE === $this->status && $active;
    }

    public static function register(string $slug, string $name, bool $anonymousAccess = false): self
    {
        return new self(slug: $slug, name: $name, anonymousAccess: $anonymousAccess);
    }

    public static function normalizeName(string $name): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($name));
    }

    public static function normalizeNameForComparison(string $name): string
    {
        return strtolower(self::normalizeName($name));
    }

    public static function normalizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = (string) preg_replace('/[^a-z0-9\-]+/', '-', $normalized);
        $normalized = (string) preg_replace('/-+/', '-', $normalized);

        return trim($normalized, '-');
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function anonymousAccess(): bool
    {
        return $this->anonymousAccess;
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

    public function updateAnonymousAccess(bool $anonymousAccess): void
    {
        $this->anonymousAccess = $anonymousAccess;
    }

    public function rename(string $name): void
    {
        $this->name = self::normalizeName($name);
    }

    public function markDeleting(): void
    {
        $this->active = false;
        $this->status = self::STATUS_DELETING;
    }
}
