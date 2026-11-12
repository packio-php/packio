<?php

declare(strict_types=1);

namespace Packio\Admin\Application\Settings;

use Common\Exception\PublicValidationException;
use Common\Provider\SourceProviderMetadata;
use Common\Provider\SourceProviderMetadataCatalog;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;

use function array_key_exists;
use function array_map;
use function array_values;

final readonly class RuntimeSettingCatalog
{
    public function __construct(private SourceProviderMetadataCatalog $sourceProviderMetadataCatalog) {}

    /** @return list<RuntimeSettingDefinition> */
    public function definitions(): array
    {
        return [
            new RuntimeSettingDefinition(
                key: ProjectSettingsApiFacadeInterface::REGISTRATION_OPEN,
                label: 'Self-registration',
                group: 'identity',
                configPath: 'identity.registration_open',
                fallbackDefault: true,
                helpText: 'Controls whether guests can create local accounts without an invitation.',
            ),
            new RuntimeSettingDefinition(
                key: ProjectSettingsApiFacadeInterface::API_DOCS_PUBLIC,
                label: 'Public API documentation',
                group: 'api',
                configPath: 'api.docs.public',
                fallbackDefault: true,
                helpText: 'Controls whether API documentation is visible without signing in.',
            ),
            new RuntimeSettingDefinition(
                key: ProjectSettingsApiFacadeInterface::HTTP_CORS_ENABLED,
                label: 'CORS',
                group: 'http',
                configPath: 'packio.cors.enabled',
                fallbackDefault: true,
                helpText: 'Controls whether Packio sends CORS responses for configured origins.',
            ),
            ...$this->sourceProviderDefinitions(),
            ...$this->oauthLoginDefinitions(),
            ...$this->sourceOAuthDefinitions(),
        ];
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(
            static fn (RuntimeSettingDefinition $runtimeSettingDefinition): string => $runtimeSettingDefinition->key,
            $this->definitions(),
        );
    }

    public function definition(string $key): RuntimeSettingDefinition
    {
        $definitions = $this->definitionsByKey();
        if (! array_key_exists($key, $definitions)) {
            throw new PublicValidationException('Unknown runtime setting key.');
        }

        return $definitions[$key];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->definitionsByKey());
    }

    /**
     * @return array<string, RuntimeSettingDefinition>
     */
    private function definitionsByKey(): array
    {
        $definitions = [];
        foreach ($this->definitions() as $runtimeSettingDefinition) {
            $definitions[$runtimeSettingDefinition->key] = $runtimeSettingDefinition;
        }

        return $definitions;
    }

    /**
     * @return list<RuntimeSettingDefinition>
     */
    private function sourceProviderDefinitions(): array
    {
        return array_map(
            fn (SourceProviderMetadata $sourceProviderMetadata): RuntimeSettingDefinition => $this->sourceProviderDefinition($sourceProviderMetadata->key, $sourceProviderMetadata->label),
            $this->sourceProviderMetadataCatalog->all(),
        );
    }

    private function sourceProviderDefinition(string $provider, string $label): RuntimeSettingDefinition
    {
        return new RuntimeSettingDefinition(
            key: 'source.providers.' . $provider . '.enabled',
            label: $label,
            group: 'source_provider',
            configPath: 'sources.providers.' . $provider . '.enabled',
            fallbackDefault: true,
            helpText: 'Controls whether this provider is available for sources and provider access.',
            providerKey: $provider,
        );
    }

    /**
     * @return list<RuntimeSettingDefinition>
     */
    private function oauthLoginDefinitions(): array
    {
        return array_map(
            fn (SourceProviderMetadata $sourceProviderMetadata): RuntimeSettingDefinition => $this->oauthLoginDefinition(
                (string) $sourceProviderMetadata->oauthProvider,
                $sourceProviderMetadata->label,
            ),
            $this->uniqueLoginOAuthBackedProviders(),
        );
    }

    /**
     * @return list<SourceProviderMetadata>
     */
    private function uniqueLoginOAuthBackedProviders(): array
    {
        $providers = [];
        foreach ($this->sourceProviderMetadataCatalog->loginOAuthBackedSourceProviders() as $sourceProviderMetadata) {
            if (null === $sourceProviderMetadata->oauthProvider) {
                continue;
            }

            if (array_key_exists((string) $sourceProviderMetadata->oauthProvider, $providers)) {
                continue;
            }

            $providers[$sourceProviderMetadata->oauthProvider] = $sourceProviderMetadata;
        }

        return array_values($providers);
    }

    private function oauthLoginDefinition(string $provider, string $label): RuntimeSettingDefinition
    {
        return new RuntimeSettingDefinition(
            key: 'oauth.' . $provider . '.enabled',
            label: $label,
            group: 'oauth_login',
            configPath: 'oauth.' . $provider . '.enabled',
            helpText: 'Controls whether this provider is available on login and registration pages.',
            providerKey: $provider,
        );
    }

    /**
     * @return list<RuntimeSettingDefinition>
     */
    private function sourceOAuthDefinitions(): array
    {
        return [
            ...array_map(
                fn (SourceProviderMetadata $sourceProviderMetadata): RuntimeSettingDefinition => $this->sourceOAuthDefinition(
                    $sourceProviderMetadata->key,
                    $sourceProviderMetadata->label,
                    'oauth.' . $sourceProviderMetadata->key . '.source.enabled',
                ),
                $this->sourceProviderMetadataCatalog->loginOAuthBackedSourceProviders(),
            ),
            $this->sourceOAuthDefinition(
                'gitlab_self_hosted',
                'GitLab Self-Hosted',
                'oauth.gitlab_self_hosted.source.enabled',
                'oauth.gitlab.self_hosted.source.enabled',
            ),
        ];
    }

    private function sourceOAuthDefinition(
        string $provider,
        string $label,
        string $key,
        ?string $configPath = null,
    ): RuntimeSettingDefinition {
        return new RuntimeSettingDefinition(
            key: $key,
            label: $label,
            group: 'source_oauth',
            configPath: $configPath ?? 'oauth.' . $provider . '.source.enabled',
            helpText: 'Controls whether this provider is available for source OAuth connections.',
            providerKey: $provider,
        );
    }
}
