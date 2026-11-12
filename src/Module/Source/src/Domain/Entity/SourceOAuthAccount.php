<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Entity;

use Cake\Chronos\Chronos;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

class SourceOAuthAccount implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    /**
     * @param list<string> $scopes
     */
    public function __construct(
        private readonly string $userId = '',
        private readonly string $provider = '',
        private string $providerUserId = '',
        private ?string $providerEmail = null,
        private ?string $providerUsername = null,
        private string $accessToken = '',
        private ?string $refreshToken = null,
        private ?Chronos $tokenExpiresAt = null,
        private array $scopes = [],
        private bool $isActive = true,
        private ?Chronos $lastUsedAt = null,
        private ?string $providerInstanceId = null,
        private ?string $providerBaseUrl = null,
        private ?string $credentialKey = null,
    ) {}

    public function userId(): string
    {
        return $this->userId;
    }

    public function providerInstanceId(): ?string
    {
        return $this->providerInstanceId;
    }

    public function providerBaseUrl(): ?string
    {
        return $this->providerBaseUrl;
    }

    public function credentialKey(): ?string
    {
        return $this->credentialKey;
    }

    public function provider(): string
    {
        return $this->provider;
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

    public function storedAccessToken(): string
    {
        return $this->accessToken;
    }

    public function storedRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function tokenExpiresAt(): ?Chronos
    {
        return $this->tokenExpiresAt;
    }

    /** @return list<string> */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function lastUsedAt(): ?Chronos
    {
        return $this->lastUsedAt;
    }

    public function updateTokens(string $accessToken, ?string $refreshToken, ?Chronos $tokenExpiresAt): void
    {
        $this->accessToken    = $accessToken;
        $this->refreshToken   = $refreshToken;
        $this->tokenExpiresAt = $tokenExpiresAt;
        $this->isActive       = true;
    }

    /** @param list<string> $scopes */
    public function syncAuthorizationContext(string $providerUserId, ?string $providerEmail, ?string $providerUsername, array $scopes): void
    {
        $this->providerUserId   = $providerUserId;
        $this->providerEmail    = $providerEmail;
        $this->providerUsername = $providerUsername;
        $this->scopes           = $scopes;
        $this->isActive         = true;
    }

    public function assignProviderInstance(?string $providerInstanceId, ?string $providerBaseUrl): void
    {
        $this->providerInstanceId = $providerInstanceId;
        $this->providerBaseUrl    = $providerBaseUrl;
    }

    public function assignCredentialKey(?string $credentialKey): void
    {
        $this->credentialKey = $credentialKey;
    }

    public function markAsInactive(): void
    {
        $this->isActive = false;
    }

    public function markAsUsed(): void
    {
        $this->lastUsedAt = new Chronos();
    }
}
