<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Common\Branding\BrandingConfig;
use Packio\Source\Application\Security\SourceProviderInstanceSecrets;
use Packio\Source\Domain\Entity\SourceProviderInstance;

use function rtrim;
use function trim;

final readonly class SourceProviderInstanceOAuthConfig
{
    public function __construct(
        private SourceProviderInstanceSecrets $sourceProviderInstanceSecrets,
        private BrandingConfig $brandingConfig,
    ) {}

    /**
     * @return null|array{base_url: string, client_id: string, client_secret: string, redirect_url: string, source_scopes: list<string>}
     */
    public function forInstance(SourceProviderInstance $sourceProviderInstance): ?array
    {
        $clientSecret = $this->sourceProviderInstanceSecrets->decryptClientSecret($sourceProviderInstance->storedClientSecret());
        if (null === $clientSecret || '' === trim($clientSecret)) {
            return null;
        }

        return [
            'base_url'      => $sourceProviderInstance->baseUrl(),
            'client_id'     => $sourceProviderInstance->clientId(),
            'client_secret' => $clientSecret,
            'redirect_url'  => rtrim($this->brandingConfig->appBaseUrl(), '/') . '/auth/oauth/' . $sourceProviderInstance->providerType() . '/source-callback',
            'source_scopes' => $this->scopes($sourceProviderInstance),
        ];
    }

    /**
     * @return list<string>
     */
    public function scopes(SourceProviderInstance $sourceProviderInstance): array
    {
        return match ($sourceProviderInstance->providerType()) {
            SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED => ['read_user', 'api', 'read_repository'],
            default                                             => [],
        };
    }
}
