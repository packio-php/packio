<?php

declare(strict_types=1);

namespace Packio\Admin\Domain\Entity;

use Ramsey\Uuid\UuidInterface;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;

use function strtolower;
use function trim;

class ProjectSetting implements EntityInterface
{
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    public function __construct(private string $key = '', private string $value = '', private ?string $updatedByUserId = null)
    {
        $this->key = $this->normalizeKey($key);
    }

    public static function put(string $key, string $value, ?string $updatedByUserId = null): self
    {
        return new self($key, $value, $updatedByUserId);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function updatedByUserId(): ?string
    {
        return $this->updatedByUserId;
    }

    public function updateValue(string $value, ?string $updatedByUserId = null): void
    {
        $this->value           = $value;
        $this->updatedByUserId = $updatedByUserId;
    }

    public function getIdentifier(): null
    {
        return null;
    }

    public function setIdentifier(int|UuidInterface $identifier): void {}

    private function normalizeKey(string $key): string
    {
        return strtolower(trim($key));
    }
}
