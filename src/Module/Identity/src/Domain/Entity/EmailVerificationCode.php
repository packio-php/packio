<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Entity;

use Cake\Chronos\Chronos;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class EmailVerificationCode implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;

    public function __construct(
        private string $userUuid = '',
        private string $codeHash = '',
        private Chronos $expiresAt = new Chronos('+10 minutes'),
        private ?Chronos $verifiedAt = null,
    ) {}

    public function userUuid(): string
    {
        return $this->userUuid;
    }

    public function setUserUuid(string $userUuid): self
    {
        $this->userUuid = $userUuid;

        return $this;
    }

    public function codeHash(): string
    {
        return $this->codeHash;
    }

    public function setCodeHash(string $codeHash): self
    {
        $this->codeHash = $codeHash;

        return $this;
    }

    public function expiresAt(): Chronos
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(Chronos $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isExpired(): bool
    {
        return new Chronos() > $this->expiresAt;
    }

    public function isVerified(): bool
    {
        return $this->verifiedAt instanceof Chronos;
    }

    public function markAsVerified(): void
    {
        $this->verifiedAt = new Chronos();
    }
}
