<?php

declare(strict_types=1);

namespace Packio\Source\Application\Security;

use Packio\Source\Domain\Entity\ProviderAuthorization;

use function trim;

final readonly class ProviderAuthorizationFactory
{
    public function __construct(private ProviderAuthorizationSecrets $providerAuthorizationSecrets) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function create(
        string $organizationId,
        string $provider,
        string $authStrategy,
        array $payload,
        string $accountName = '',
    ): ProviderAuthorization {
        return ProviderAuthorization::authorize(
            organizationId: trim($organizationId),
            provider: trim($provider),
            authStrategy: trim($authStrategy),
            configuration: $this->providerAuthorizationSecrets->encryptConfiguration(
                $this->normalizeConfiguration($payload),
            ),
            accountName: trim($accountName),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createFromOAuth(
        string $organizationId,
        string $provider,
        string $sourceOAuthAccountId,
        string $authStrategy,
        array $payload,
        string $accountName = '',
    ): ProviderAuthorization {
        return ProviderAuthorization::authorizeFromOAuth(
            organizationId: trim($organizationId),
            provider: trim($provider),
            sourceOAuthAccountId: trim($sourceOAuthAccountId),
            authStrategy: trim($authStrategy),
            configuration: $this->providerAuthorizationSecrets->encryptConfiguration(
                $this->normalizeConfiguration($payload),
            ),
            accountName: trim($accountName),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function normalizeConfiguration(array $payload): array
    {
        return [
            'token'         => trim((string) ($payload['token'] ?? '')),
            'account_id'    => trim((string) ($payload['account_id'] ?? '')),
            'resource_name' => trim((string) ($payload['resource_name'] ?? '')),
            'resource_url'  => trim((string) ($payload['resource_url'] ?? '')),
            ...$payload,
        ];
    }
}
