<?php

declare(strict_types=1);

namespace Common\Branding;

use function is_array;
use function is_int;
use function parse_url;
use function sprintf;
use function str_contains;
use function trim;

final readonly class BrandingConfig
{
    private BrandingOptions $brandingOptions;

    /**
     * @param array<string, mixed>|BrandingOptions $options
     */
    public function __construct(array|BrandingOptions $options = new BrandingOptions())
    {
        $this->brandingOptions = is_array($options) ? BrandingOptions::fromArray($options) : $options;
    }

    public function productName(): string
    {
        return $this->brandingOptions->productName;
    }

    public function tagline(): string
    {
        return $this->brandingOptions->tagline;
    }

    public function registryBaseDomain(): string
    {
        return $this->hostFromAuthority($this->brandingOptions->registryBaseDomain ?? $this->brandingOptions->domain);
    }

    public function registryHost(string $organizationSlug): string
    {
        $host = sprintf('%s.%s', $organizationSlug, $this->registryBaseDomain());
        $port = $this->registryPort();

        return is_int($port) ? sprintf('%s:%d', $host, $port) : $host;
    }

    public function registryBaseUrl(string $organizationSlug): string
    {
        $baseUrl = $this->brandingOptions->appUrl;
        $scheme  = (string) parse_url($baseUrl, PHP_URL_SCHEME);

        return sprintf(
            '%s://%s',
            '' !== $scheme ? $scheme : 'https',
            $this->registryHost($organizationSlug),
        );
    }

    public function registryTokenPrefix(): string
    {
        return $this->brandingOptions->registryTokenPrefix;
    }

    public function registryAuthRealm(): string
    {
        return $this->brandingOptions->registryAuthRealm;
    }

    public function httpUserAgent(): string
    {
        return $this->brandingOptions->httpUserAgent;
    }

    public function appBaseUrl(): string
    {
        return $this->brandingOptions->appUrl;
    }

    public function themeStorageKey(): string
    {
        return $this->brandingOptions->themeStorageKey;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'name'                    => $this->brandingOptions->productName,
            'tagline'                 => $this->brandingOptions->tagline,
            'domain'                  => $this->brandingOptions->domain,
            'shortName'               => $this->brandingOptions->shortName,
            'composerRepositoryAlias' => $this->brandingOptions->composerRepositoryAlias,
            'registryTokenPrefix'     => $this->brandingOptions->registryTokenPrefix,
            'registryAuthRealm'       => $this->brandingOptions->registryAuthRealm,
            'httpUserAgent'           => $this->brandingOptions->httpUserAgent,
            'appBaseUrl'              => $this->brandingOptions->appUrl,
            'themeStorageKey'         => $this->brandingOptions->themeStorageKey,
        ];
    }

    private function registryPort(): ?int
    {
        $port = $this->portFromAuthority($this->brandingOptions->registryBaseDomain ?? $this->brandingOptions->domain);

        return is_int($port) ? $port : $this->appPort();
    }

    private function appPort(): ?int
    {
        $port = parse_url($this->brandingOptions->appUrl, PHP_URL_PORT);

        return is_int($port) ? $port : null;
    }

    private function hostFromAuthority(string $authority): string
    {
        $parts = $this->parseAuthority($authority);

        return (string) ($parts['host'] ?? trim($authority));
    }

    private function portFromAuthority(string $authority): ?int
    {
        $parts = $this->parseAuthority($authority);
        $port  = $parts['port'] ?? null;

        return is_int($port) ? $port : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAuthority(string $authority): array
    {
        $authority = trim($authority);
        if ('' === $authority) {
            return [];
        }

        $value = str_contains($authority, '://') ? $authority : 'https://' . $authority;
        $parts = parse_url($value);

        return false !== $parts ? $parts : [];
    }
}
