<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Entity;

use Cake\Chronos\Chronos;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class ApiAccessToken implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    /**
     * @param list<string> $scopes
     */
    public function __construct(
        private string $userId = '',
        private string $name = '',
        private string $tokenHash = '',
        private string $tokenPrefix = '',
        private array $scopes = [],
        private ?Chronos $lastUsedAt = null,
        private ?Chronos $expiresAt = null,
        private ?Chronos $revokedAt = null,
    ) {}

    /**
     * @param list<string> $scopes
     */
    public static function issue(
        string $userId,
        string $name,
        string $tokenHash,
        string $tokenPrefix,
        array $scopes,
        ?Chronos $expiresAt = null,
    ): self {
        return new self(
            userId: $userId,
            name: $name,
            tokenHash: $tokenHash,
            tokenPrefix: $tokenPrefix,
            scopes: $scopes,
            expiresAt: $expiresAt,
        );
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function tokenPrefix(): string
    {
        return $this->tokenPrefix;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function lastUsedAt(): ?Chronos
    {
        return $this->lastUsedAt;
    }

    public function expiresAt(): ?Chronos
    {
        return $this->expiresAt;
    }

    public function revokedAt(): ?Chronos
    {
        return $this->revokedAt;
    }

    public function markUsed(Chronos $usedAt): void
    {
        $this->lastUsedAt = $usedAt;
    }

    public function revoke(Chronos $revokedAt): void
    {
        $this->revokedAt ??= $revokedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof Chronos;
    }

    public function isExpired(Chronos $now): bool
    {
        return $this->expiresAt instanceof Chronos && $this->expiresAt <= $now;
    }
}
