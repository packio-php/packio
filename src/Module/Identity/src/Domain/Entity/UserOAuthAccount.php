<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Entity;

use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class UserOAuthAccount implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    /** @param list<string> $scopes */
    public function __construct(
        private readonly string $userId = '',
        private readonly OAuthProvider $oAuthProvider = OAuthProvider::Github,
        private readonly string $providerUserId = '',
        private readonly ?string $providerEmail = null,
        private readonly ?string $providerUsername = null,
        private ?array $scopes = null,
    ) {}

    /** @param list<string> $scopes */
    public static function register(
        string $userId,
        OAuthProvider $oAuthProvider,
        string $providerUserId,
        ?string $providerEmail,
        ?string $providerUsername,
        array $scopes = []
    ): self {
        return new self(
            userId: $userId,
            oAuthProvider: $oAuthProvider,
            providerUserId: $providerUserId,
            providerEmail: $providerEmail,
            providerUsername: $providerUsername,
            scopes: $scopes,
        );
    }

    public static function create(
        string $userId,
        OAuthProvider $oAuthProvider,
        string $providerUserId,
        ?string $providerEmail = null,
        ?string $providerUsername = null
    ): self {
        return new self(
            userId: $userId,
            oAuthProvider: $oAuthProvider,
            providerUserId: $providerUserId,
            providerEmail: $providerEmail,
            providerUsername: $providerUsername,
        );
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function provider(): OAuthProvider
    {
        return $this->oAuthProvider;
    }

    public function providerUserId(): string
    {
        return $this->providerUserId;
    }

    public function providerEmail(): ?string
    {
        return $this->providerEmail;
    }

    public function providerUsername(): ?string
    {
        return $this->providerUsername;
    }

    /** @return list<string> */
    public function scopes(): array
    {
        return $this->scopes ?? [];
    }

    /** @param list<string> $scopes */
    public function updateScopes(array $scopes): void
    {
        $this->scopes = $scopes;
    }
}
