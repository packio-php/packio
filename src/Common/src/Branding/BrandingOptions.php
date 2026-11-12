<?php

declare(strict_types=1);

namespace Common\Branding;

final readonly class BrandingOptions
{
    public function __construct(
        public string $productName = 'Packio',
        public string $tagline = 'Lightweight private package registry for Composer',
        public string $domain = 'packio.localhost',
        public ?string $registryBaseDomain = null,
        public string $shortName = 'packio',
        public string $composerRepositoryAlias = 'packio',
        public string $registryTokenPrefix = 'packio_rt_',
        public string $registryAuthRealm = 'Packio',
        public string $httpUserAgent = 'Packio/0.1',
        public string $themeStorageKey = 'packio-theme',
        public string $appUrl = 'http://localhost:8080',
    ) {}

    /**
     * @param array{
     *     product_name?: string,
     *     tagline?: string,
     *     domain?: string,
     *     registry_base_domain?: string,
     *     short_name?: string,
     *     composer_repository_alias?: string,
     *     registry_token_prefix?: string,
     *     registry_auth_realm?: string,
     *     http_user_agent?: string,
     *     theme_storage_key?: string,
     *     app_url?: string
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            productName: $config['product_name'] ?? 'Packio',
            tagline: $config['tagline'] ?? 'Lightweight private package registry for Composer',
            domain: $config['domain'] ?? 'packio.localhost',
            registryBaseDomain: $config['registry_base_domain'] ?? null,
            shortName: $config['short_name'] ?? 'packio',
            composerRepositoryAlias: $config['composer_repository_alias'] ?? 'packio',
            registryTokenPrefix: $config['registry_token_prefix'] ?? 'packio_rt_',
            registryAuthRealm: $config['registry_auth_realm'] ?? 'Packio',
            httpUserAgent: $config['http_user_agent'] ?? 'Packio/0.1',
            themeStorageKey: $config['theme_storage_key'] ?? 'packio-theme',
            appUrl: $config['app_url'] ?? 'http://localhost:8080',
        );
    }
}
