<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Entity;

use Cake\Chronos\Chronos;
use InvalidArgumentException;
use Packio\Identity\Api\UserBanReason;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function hash;
use function in_array;
use function mb_strlen;
use function sprintf;
use function strtolower;
use function trim;

class User implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $email = '',
        private string $displayName = '',
        private string $passwordHash = '',
        private array $roles = ['user'],
        private ?Chronos $emailVerifiedAt = null,
        private ?Chronos $bannedAt = null,
        private ?string $banReason = null,
        private ?string $bannedByUserId = null,
    ) {
        $this->email       = $this->normalizeEmail($email);
        $this->displayName = trim($displayName);
        $this->roles       = $this->normalizeRoles($roles);
    }

    /** @param list<string> $roles */
    public static function register(string $email, string $displayName, string $passwordHash, array $roles = ['user']): self
    {
        return new self(email: $email, displayName: $displayName, passwordHash: $passwordHash, roles: $roles);
    }

    public function email(): string
    {
        return $this->email;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function credentialsFingerprint(): string
    {
        return hash('sha256', $this->passwordHash);
    }

    public function hasPassword(): bool
    {
        return '' !== $this->passwordHash;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function emailVerifiedAt(): ?Chronos
    {
        return $this->emailVerifiedAt;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt instanceof Chronos;
    }

    public function bannedAt(): ?Chronos
    {
        return $this->bannedAt;
    }

    public function banReason(): ?string
    {
        return $this->banReason;
    }

    public function bannedByUserId(): ?string
    {
        return $this->bannedByUserId;
    }

    public function isBanned(): bool
    {
        return $this->bannedAt instanceof Chronos;
    }

    public function ban(string $reason, string $actorId): void
    {
        $normalizedReason  = $this->normalizeBanReason($reason);
        $normalizedActorId = $this->normalizeActorId($actorId);

        $this->bannedAt       = new Chronos();
        $this->banReason      = $normalizedReason;
        $this->bannedByUserId = $normalizedActorId;
    }

    public function unban(): void
    {
        $this->bannedAt       = null;
        $this->banReason      = null;
        $this->bannedByUserId = null;
    }

    public function markEmailAsVerified(): void
    {
        $this->emailVerifiedAt ??= new Chronos();
    }

    public function updateProfile(string $email, string $displayName): void
    {
        $this->email       = $this->normalizeEmail($email);
        $this->displayName = trim($displayName);
    }

    public function updatePasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    /** @param list<string> $roles */
    public function replaceRoles(array $roles): void
    {
        $this->roles = $this->normalizeRoles($roles);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function normalizeBanReason(string $reason): string
    {
        $normalizedReason = trim($reason);
        $length           = mb_strlen($normalizedReason);

        if ($length < UserBanReason::MIN_LENGTH || $length > UserBanReason::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Ban reason must contain between %d and %d characters.',
                UserBanReason::MIN_LENGTH,
                UserBanReason::MAX_LENGTH,
            ));
        }

        return $normalizedReason;
    }

    private function normalizeActorId(string $actorId): string
    {
        $normalizedActorId = trim($actorId);

        if ('' === $normalizedActorId) {
            throw new InvalidArgumentException('Banning actor ID must not be empty.');
        }

        return $normalizedActorId;
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private function normalizeRoles(array $roles): array
    {
        $normalized = array_map(trim(...), $roles)
            |> (fn ($x) => array_filter($x, static fn (string $role): bool => '' !== $role))
            |> array_unique(...)
            |> array_values(...);
        if ([] === $normalized) {
            return ['user'];
        }

        if (! in_array('user', $normalized, true) && ! in_array('admin', $normalized, true)) {
            $normalized[] = 'user';
        }

        return $normalized;
    }
}
