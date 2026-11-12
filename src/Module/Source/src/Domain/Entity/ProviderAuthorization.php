<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Entity;

use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosCreateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasChronosUpdateTrait;
use Sirix\Cycle\Extension\Entity\Trait\HasUuidIdentifierTrait;

use function is_string;

class ProviderAuthorization implements EntityInterface
{
    use HasUuidIdentifierTrait;
    use HasChronosCreateTrait;
    use HasChronosUpdateTrait;

    private ?string $sourceOAuthAccountId = null;

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        private readonly string $organizationId = '',
        private readonly string $provider = '',
        private readonly string $authStrategy = 'token',
        private string $status = 'active',
        private string $accountName = '',
        private array $configuration = [],
        private ?string $providerInstanceId = null,
    ) {}

    /**
     * @param array<string, mixed> $configuration
     */
    public static function authorize(
        string $organizationId,
        string $provider,
        string $authStrategy,
        array $configuration,
        string $accountName = '',
    ): self {
        return new self(
            organizationId: $organizationId,
            provider: $provider,
            authStrategy: $authStrategy,
            accountName: $accountName,
            configuration: $configuration,
        );
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function authStrategy(): string
    {
        return $this->authStrategy;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function accountName(): string
    {
        return $this->accountName;
    }

    /**
     * @return array<string, mixed>
     */
    public function configuration(): array
    {
        return $this->configuration;
    }

    public function updateStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function replaceConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function secret(string $key = 'token'): ?string
    {
        $value = $this->configuration[$key] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }

    public function sourceOAuthAccountId(): ?string
    {
        return $this->sourceOAuthAccountId;
    }

    public function providerInstanceId(): ?string
    {
        return $this->providerInstanceId;
    }

    public function assignProviderInstance(?string $providerInstanceId): void
    {
        $this->providerInstanceId = $providerInstanceId;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public static function authorizeFromOAuth(
        string $organizationId,
        string $provider,
        string $sourceOAuthAccountId,
        string $authStrategy,
        array $configuration,
        string $accountName = '',
    ): self {
        $self = new self(
            organizationId: $organizationId,
            provider: $provider,
            authStrategy: $authStrategy,
            accountName: $accountName,
            configuration: $configuration,
        );
        $self->sourceOAuthAccountId = $sourceOAuthAccountId;

        return $self;
    }
}
