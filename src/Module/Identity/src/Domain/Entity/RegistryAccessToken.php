<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Entity;

use Cake\Chronos\Chronos;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class RegistryAccessToken implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    /**
     * @param list<string> $permissions
     */
    public function __construct(
        private string $organizationId = '',
        private readonly string $ownerUserId = '',
        private readonly string $name = '',
        private readonly string $tokenHash = '',
        private readonly string $maskedToken = '',
        private readonly array $permissions = [],
        private ?Chronos $lastUsedAt = null,
        private readonly string $encryptedToken = '',
    ) {}

    /** @param list<string> $permissions */
    public static function issue(
        string $organizationId,
        string $ownerUserId,
        string $name,
        string $tokenHash,
        string $maskedToken,
        array $permissions,
        string $encryptedToken = ''
    ): self {
        return new self(organizationId: $organizationId, ownerUserId: $ownerUserId, name: $name, tokenHash: $tokenHash, maskedToken: $maskedToken, permissions: $permissions, encryptedToken: $encryptedToken);
    }

    public function markUsed(Chronos $usedAt): void
    {
        $this->lastUsedAt = $usedAt;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function ownerUserId(): string
    {
        return $this->ownerUserId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function maskedToken(): string
    {
        return $this->maskedToken;
    }

    public function storedEncryptedToken(): string
    {
        return $this->encryptedToken;
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function lastUsedAt(): ?Chronos
    {
        return $this->lastUsedAt;
    }
}
