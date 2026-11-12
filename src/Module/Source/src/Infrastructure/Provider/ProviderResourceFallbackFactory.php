<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider;

use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Entity\ProviderAuthorization;

use function trim;

final readonly class ProviderResourceFallbackFactory
{
    /**
     * @return list<ProviderResourceData>
     */
    public function fromAuthorizationConfiguration(ProviderAuthorization $providerAuthorization): array
    {
        $configuration = $providerAuthorization->configuration();
        $resourceName  = trim((string) ($configuration['resource_name'] ?? ''));
        $resourceUrl   = trim((string) ($configuration['resource_url'] ?? ''));

        if ('' === $resourceName || '' === $resourceUrl) {
            return [];
        }

        return [
            new ProviderResourceData(
                id: $resourceName,
                name: $resourceName,
                type: 'git',
                url: $resourceUrl,
                defaultBranch: 'main',
            ),
        ];
    }
}
