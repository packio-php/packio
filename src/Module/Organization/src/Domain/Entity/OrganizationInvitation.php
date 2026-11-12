<?php

declare(strict_types=1);

namespace Packio\Organization\Domain\Entity;

use Cake\Chronos\Chronos;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

use function bin2hex;
use function hash;
use function random_bytes;
use function trim;

class OrganizationInvitation implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    private string $tokenHash;

    public function __construct(
        private string $organizationId = '',
        private string $invitedByUserId = '',
        private string $token = '',
        private string $role = 'member',
        private ?string $acceptedByUserId = null,
        private ?Chronos $expiresAt = null,
        private ?Chronos $acceptedAt = null,
        private ?Chronos $revokedAt = null,
    ) {
        $this->organizationId   = trim($organizationId);
        $this->invitedByUserId  = trim($invitedByUserId);
        $this->token            = '' !== trim($token) ? trim($token) : $this->issueToken();
        $this->tokenHash        = self::hashToken($this->token);
        $this->role             = 'owner' === $role ? 'owner' : 'member';
        $this->acceptedByUserId = null !== $acceptedByUserId ? trim($acceptedByUserId) : null;
        $this->expiresAt ??= new Chronos('+7 days');
    }

    public static function invite(string $organizationId, string $invitedByUserId, string $role = 'member'): self
    {
        return new self(organizationId: $organizationId, invitedByUserId: $invitedByUserId, role: $role);
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function invitedByUserId(): string
    {
        return $this->invitedByUserId;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    /**
     * Replaces the stored form of the token (e.g. its ciphertext); the lookup hash stays the same.
     */
    public function storeToken(string $storedToken): void
    {
        $this->token = $storedToken;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function role(): string
    {
        return $this->role;
    }

    public function acceptedByUserId(): ?string
    {
        return $this->acceptedByUserId;
    }

    public function expiresAt(): ?Chronos
    {
        return $this->expiresAt;
    }

    public function acceptedAt(): ?Chronos
    {
        return $this->acceptedAt;
    }

    public function revokedAt(): ?Chronos
    {
        return $this->revokedAt;
    }

    public function status(): string
    {
        if ($this->revokedAt instanceof Chronos) {
            return 'revoked';
        }

        if ($this->acceptedAt instanceof Chronos) {
            return 'accepted';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'pending';
    }

    public function accept(string $userId): void
    {
        $this->acceptedByUserId = trim($userId);
        $this->acceptedAt       = new Chronos();
    }

    public function revoke(): void
    {
        $this->revokedAt = new Chronos();
    }

    public function isExpired(?Chronos $now = null): bool
    {
        if (! $this->expiresAt instanceof Chronos) {
            return false;
        }

        return $this->expiresAt < ($now ?? new Chronos());
    }

    public function isPending(): bool
    {
        return 'pending' === $this->status();
    }

    private function issueToken(): string
    {
        return 'orgi_' . bin2hex(random_bytes(24));
    }
}
