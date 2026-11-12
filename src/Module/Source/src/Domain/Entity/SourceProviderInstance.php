<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Entity;

use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

use function in_array;

class SourceProviderInstance implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    public const PROVIDER_GITLAB_SELF_HOSTED = 'gitlab_self_hosted';
    public const PROVIDER_FORGEJO            = 'forgejo';
    public const PROVIDER_GITEA              = 'gitea';

    public const AUTH_MODE_OAUTH2           = 'oauth2';
    public const AUTH_MODE_TOKEN            = 'token';
    public const AUTH_MODE_OAUTH2_AND_TOKEN = 'oauth2_and_token';

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /** @var list<string> */
    public const SUPPORTED_PROVIDER_TYPES = [
        self::PROVIDER_GITLAB_SELF_HOSTED,
        self::PROVIDER_FORGEJO,
        self::PROVIDER_GITEA,
    ];

    /** @var list<string> */
    public const SUPPORTED_AUTH_MODES = [
        self::AUTH_MODE_OAUTH2,
        self::AUTH_MODE_TOKEN,
        self::AUTH_MODE_OAUTH2_AND_TOKEN,
    ];

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $organizationId = '',
        private readonly string $providerType = '',
        private string $label = '',
        private string $baseUrl = '',
        private string $clientId = '',
        private ?string $clientSecret = null,
        private string $authMode = self::AUTH_MODE_OAUTH2_AND_TOKEN,
        private string $status = self::STATUS_ACTIVE,
        private array $metadata = [],
        private ?string $createdByUserId = null,
    ) {
        $this->authMode = in_array($authMode, self::SUPPORTED_AUTH_MODES, true)
            ? $authMode
            : self::AUTH_MODE_OAUTH2_AND_TOKEN;
        $this->status = self::STATUS_INACTIVE === $status ? self::STATUS_INACTIVE : self::STATUS_ACTIVE;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $organizationId,
        string $providerType,
        string $label,
        string $baseUrl,
        string $clientId,
        ?string $clientSecret,
        string $authMode,
        ?string $createdByUserId = null,
        array $metadata = [],
    ): self {
        return new self(
            organizationId: $organizationId,
            providerType: $providerType,
            label: $label,
            baseUrl: $baseUrl,
            clientId: $clientId,
            clientSecret: $clientSecret,
            authMode: $authMode,
            metadata: $metadata,
            createdByUserId: $createdByUserId,
        );
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function providerType(): string
    {
        return $this->providerType;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function storedClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    public function authMode(): string
    {
        return $this->authMode;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return self::STATUS_ACTIVE === $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function createdByUserId(): ?string
    {
        return $this->createdByUserId;
    }

    public function supportsOAuth(): bool
    {
        return self::AUTH_MODE_OAUTH2 === $this->authMode || self::AUTH_MODE_OAUTH2_AND_TOKEN === $this->authMode;
    }

    public function supportsToken(): bool
    {
        return self::AUTH_MODE_TOKEN === $this->authMode || self::AUTH_MODE_OAUTH2_AND_TOKEN === $this->authMode;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function updateDetails(
        string $label,
        string $baseUrl,
        string $clientId,
        string $authMode,
        array $metadata = []
    ): void {
        $this->label    = $label;
        $this->baseUrl  = $baseUrl;
        $this->clientId = $clientId;
        $this->authMode = in_array($authMode, self::SUPPORTED_AUTH_MODES, true)
            ? $authMode
            : $this->authMode;
        $this->metadata = $metadata;
    }

    public function replaceClientSecret(?string $clientSecret): void
    {
        $this->clientSecret = $clientSecret;
    }

    public function activate(): void
    {
        $this->status = self::STATUS_ACTIVE;
    }

    public function deactivate(): void
    {
        $this->status = self::STATUS_INACTIVE;
    }
}
