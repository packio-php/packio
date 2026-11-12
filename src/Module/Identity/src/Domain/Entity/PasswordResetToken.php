<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Entity;

use Cake\Chronos\Chronos;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class PasswordResetToken implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;

    public function __construct(
        private string $userUuid = '',
        private string $selector = '',
        private string $tokenHash = '',
        private Chronos $expiresAt = new Chronos('+60 minutes'),
        private ?Chronos $usedAt = null,
        private ?string $requestedIp = null,
        private ?string $consumedIp = null,
        private ?string $userAgent = null,
    ) {}

    public function userUuid(): string
    {
        return $this->userUuid;
    }

    public function selector(): string
    {
        return $this->selector;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function requestedIp(): ?string
    {
        return $this->requestedIp;
    }

    public function setRequestedIp(?string $requestedIp): self
    {
        $this->requestedIp = $requestedIp;

        return $this;
    }

    public function consumedIp(): ?string
    {
        return $this->consumedIp;
    }

    public function setConsumedIp(?string $consumedIp): self
    {
        $this->consumedIp = $consumedIp;

        return $this;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function isExpired(): bool
    {
        return new Chronos() > $this->expiresAt;
    }

    public function isUsed(): bool
    {
        return $this->usedAt instanceof Chronos;
    }

    public function markAsUsed(): void
    {
        $this->usedAt = new Chronos();
    }
}
