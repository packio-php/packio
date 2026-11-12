<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider;

use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;

use function trim;

final readonly class SourceRegistrationFactory
{
    /**
     * @param array<string, mixed> $providerConfig
     */
    public function registerGitSource(
        string $organizationId,
        string $provider,
        string $canonicalUrl,
        string $displayName,
        string $defaultBranch,
        string $visibility,
        ?ProviderAuthorization $providerAuthorization,
        string $providerResourceId,
        array $providerConfig,
    ): Source {
        return Source::register(
            organizationId: $organizationId,
            provider: $provider,
            canonicalUrl: $canonicalUrl,
            displayName: $displayName,
            defaultRef: '' !== trim($defaultBranch) ? trim($defaultBranch) : 'main',
            visibility: 'private' === $visibility ? 'private' : 'public',
            providerAuthorizationId: $providerAuthorization?->getIdentifier()->toString(),
            providerResourceId: $providerResourceId,
            providerConfig: $providerConfig,
        );
    }
}
