<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth;

use Common\Provider\SourceProviderMetadata;
use Common\Provider\SourceProviderMetadataCatalog;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Identity\Api\Dto\OAuthProviderAvailability;
use Packio\Identity\Api\OAuthProvider;

use function array_filter;
use function array_find;
use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function strtolower;
use function trim;

final readonly class OAuthProviderAvailabilityResolver
{
    /**
     * @param array<string, mixed> $oauthConfig
     */
    public function __construct(
        private array $oauthConfig,
        private SourceProviderMetadataCatalog $sourceProviderMetadataCatalog,
        private ProjectSettingsApiFacadeInterface $projectSettingsApiFacade,
    ) {}

    /** @return list<OAuthProviderAvailability> */
    public function all(): array
    {
        return array_map(
            fn (SourceProviderMetadata $sourceProviderMetadata): OAuthProviderAvailability => $this->metadata(
                provider: (string) $sourceProviderMetadata->oauthProvider,
                supportsAuthLogin: true,
                supportsSourceConnect: true,
                sourceProvider: $sourceProviderMetadata->key,
                visibleOnAuthPages: true,
                visibleOnSources: true,
            ),
            $this->sourceProviderMetadataCatalog->loginOAuthBackedSourceProviders(),
        );
    }

    /** @return list<OAuthProviderAvailability> */
    public function forAuthPages(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (OAuthProviderAvailability $oAuthProviderAvailability): bool => $oAuthProviderAvailability->visibleOnAuthPages,
        ));
    }

    public function forSourceProvider(string $sourceProvider): ?OAuthProviderAvailability
    {
        foreach ($this->all() as $oAuthProviderAvailability) {
            if ($oAuthProviderAvailability->sourceProvider === $sourceProvider) {
                return $oAuthProviderAvailability;
            }
        }

        return null;
    }

    public function forProvider(string $provider): ?OAuthProviderAvailability
    {
        $normalizedProvider = strtolower($provider);

        return array_find($this->all(), fn ($metadata) => $metadata->provider === $normalizedProvider);
    }

    public function resolveAuthProvider(string $provider): ?OAuthProvider
    {
        $metadata = $this->forProvider($provider);
        if (
            ! $metadata instanceof OAuthProviderAvailability
            || ! $metadata->isAuthLoginAvailable()
        ) {
            return null;
        }

        return OAuthProvider::tryFrom($metadata->provider);
    }

    public function resolveSourceConnectProvider(string $provider): ?OAuthProvider
    {
        $metadata = $this->forProvider($provider);
        if (
            ! $metadata instanceof OAuthProviderAvailability
            || ! $metadata->isSourceConnectAvailable()
        ) {
            return null;
        }

        return OAuthProvider::tryFrom($metadata->provider);
    }

    public function isEnabled(string $provider): bool
    {
        $metadata = $this->forProvider($provider);

        return $metadata instanceof OAuthProviderAvailability && $metadata->enabled;
    }

    public function supportsAuthLogin(string $provider): bool
    {
        $metadata = $this->forProvider($provider);

        return $metadata instanceof OAuthProviderAvailability && $metadata->supportsAuthLogin;
    }

    public function supportsSourceConnect(string $provider): bool
    {
        $metadata = $this->forProvider($provider);

        return $metadata instanceof OAuthProviderAvailability && $metadata->isSourceConnectAvailable();
    }

    public function gitLabSelfHostedSourceConnectEnabled(): bool
    {
        $selfHostedConfig = $this->gitLabSelfHostedConfig();
        $sourceConfig     = $selfHostedConfig['source'] ?? [];
        $sourceConfig     = is_array($sourceConfig) ? $sourceConfig : [];

        return $this->runtimeBoolean(
            'oauth.gitlab_self_hosted.source.enabled',
            (bool) ($sourceConfig['enabled'] ?? false),
        );
    }

    public function gitLabSelfHostedSourceInstanceAvailable(
        bool $instanceExists,
        bool $instanceActive,
        bool $supportsOAuth,
        bool $credentialsConfigured,
    ): bool {
        return $this->gitLabSelfHostedSourceConnectEnabled()
            && $instanceExists
            && $instanceActive
            && $supportsOAuth
            && $credentialsConfigured;
    }

    private function metadata(
        string $provider,
        bool $supportsAuthLogin,
        bool $supportsSourceConnect,
        ?string $sourceProvider,
        bool $visibleOnAuthPages,
        bool $visibleOnSources,
    ): OAuthProviderAvailability {
        return new OAuthProviderAvailability(
            provider: $provider,
            enabled: $this->authLoginEnabled($provider),
            supportsAuthLogin: $supportsAuthLogin,
            supportsSourceConnect: $supportsSourceConnect,
            sourceProvider: $sourceProvider,
            visibleOnAuthPages: $visibleOnAuthPages,
            visibleOnSources: $visibleOnSources,
            sourceConnectEnabled: $this->sourceConnectEnabled($provider),
            authCredentialsConfigured: $this->loginCredentialsConfigured($provider),
            sourceCredentialsConfigured: $this->sourceCredentialsConfigured($provider),
        );
    }

    private function authLoginEnabled(string $provider): bool
    {
        return $this->runtimeBoolean(
            'oauth.' . $provider . '.enabled',
            (bool) ($this->providerConfig($provider)['enabled'] ?? false),
        ) && $this->loginCredentialsConfigured($provider);
    }

    private function sourceConnectEnabled(string $provider): bool
    {
        $sourceConfig = $this->sourceConfig($provider);

        return $this->runtimeBoolean(
            'oauth.' . $provider . '.source.enabled',
            (bool) ($sourceConfig['enabled'] ?? false),
        ) && $this->sourceCredentialsConfigured($provider);
    }

    private function loginCredentialsConfigured(string $provider): bool
    {
        $config = $this->providerConfig($provider);

        return null !== $this->nonEmptyString($config['client_id'] ?? null)
            && null !== $this->nonEmptyString($config['client_secret'] ?? null);
    }

    private function sourceCredentialsConfigured(string $provider): bool
    {
        $sourceConfig = $this->sourceConfig($provider);

        return null !== $this->nonEmptyString($sourceConfig['client_id'] ?? null)
            && null !== $this->nonEmptyString($sourceConfig['client_secret'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function providerConfig(string $provider): array
    {
        $config = $this->oauthConfig[$provider] ?? [];

        return is_array($config) ? $config : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceConfig(string $provider): array
    {
        $sourceConfig = $this->providerConfig($provider)['source'] ?? [];

        return is_array($sourceConfig) ? $sourceConfig : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function gitLabSelfHostedConfig(): array
    {
        $selfHostedConfig = $this->providerConfig('gitlab')['self_hosted'] ?? [];

        return is_array($selfHostedConfig) ? $selfHostedConfig : [];
    }

    private function runtimeBoolean(string $key, bool $fallback): bool
    {
        return $this->projectSettingsApiFacade->booleanValue($key, $fallback);
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
