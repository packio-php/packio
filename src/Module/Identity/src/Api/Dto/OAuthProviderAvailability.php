<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Dto;

use Packio\Identity\Api\OAuthProvider;

final readonly class OAuthProviderAvailability
{
    public function __construct(
        public string $provider,
        public bool $enabled,
        public bool $supportsAuthLogin,
        public bool $supportsSourceConnect,
        public ?string $sourceProvider,
        public bool $visibleOnAuthPages,
        public bool $visibleOnSources,
        public bool $sourceConnectEnabled,
        public bool $authCredentialsConfigured = false,
        public bool $sourceCredentialsConfigured = false,
    ) {}

    public function isAuthLoginAvailable(): bool
    {
        return $this->supportsAuthLogin && $this->enabled;
    }

    public function isSourceConnectAvailable(): bool
    {
        return $this->supportsSourceConnect && $this->sourceConnectEnabled;
    }

    /**
     * @return array{provider: string, label: string, enabled: bool}
     */
    public function toAuthPagePayload(): array
    {
        $provider = OAuthProvider::tryFrom($this->provider);

        return [
            'provider' => $this->provider,
            'label'    => $provider?->label() ?? $this->provider,
            'enabled'  => $this->enabled,
        ];
    }
}
