<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\OAuth;

use function array_is_list;
use function is_array;
use function is_string;
use function trim;

final readonly class OAuthClientConfigResolver
{
    /**
     * @param array<string, mixed> $oauthConfig
     */
    public function __construct(private array $oauthConfig) {}

    /**
     * @return array<string, mixed>
     */
    public function loginConfig(string $provider): array
    {
        $config = $this->providerConfig($provider);
        unset($config['source']);

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceConfig(string $provider): array
    {
        $config       = $this->loginConfig($provider);
        $sourceConfig = $this->sourceOverrideConfig($provider);

        $config['client_id']     = $this->nonEmptyString($sourceConfig['client_id'] ?? null) ?? '';
        $config['client_secret'] = $this->nonEmptyString($sourceConfig['client_secret'] ?? null) ?? '';

        $sourceRedirectUrl = $this->nonEmptyString($sourceConfig['redirect_url'] ?? null);
        if (null !== $sourceRedirectUrl) {
            $config['redirect_url'] = $sourceRedirectUrl;
        } else {
            $config['redirect_url'] = $this->nonEmptyString($sourceConfig['default_redirect_url'] ?? null) ?? '';
        }

        $sourceScopes            = $this->resolvedSourceScopes($sourceConfig);
        $config['scopes']        = $sourceScopes;
        $config['source_scopes'] = $sourceScopes;

        return $config;
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
    private function sourceOverrideConfig(string $provider): array
    {
        $config       = $this->providerConfig($provider);
        $sourceConfig = $config['source'] ?? [];

        return is_array($sourceConfig) ? $sourceConfig : [];
    }

    /**
     * @param array<string, mixed> $sourceConfig
     *
     * @return list<string>
     */
    private function resolvedSourceScopes(array $sourceConfig): array
    {
        $sourceScopes = $sourceConfig['scopes'] ?? null;
        if (is_array($sourceScopes) && array_is_list($sourceScopes)) {
            return $sourceScopes;
        }

        return [];
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
