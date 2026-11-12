<?php

declare(strict_types=1);

namespace Common\Provider;

use function array_filter;
use function array_find;
use function array_values;

final readonly class SourceProviderMetadataCatalog
{
    /**
     * @return list<SourceProviderMetadata>
     */
    public function all(): array
    {
        return [
            new SourceProviderMetadata(
                key: 'github',
                label: 'GitHub',
                oauthProvider: 'github',
            ),
            new SourceProviderMetadata(
                key: 'gitlab',
                label: 'GitLab',
                oauthProvider: 'gitlab',
            ),
            new SourceProviderMetadata(
                key: 'gitlab_self_hosted',
                label: 'GitLab Self-Hosted',
                oauthProvider: 'gitlab',
                supportsSelfHostedInstances: true,
            ),
            new SourceProviderMetadata(
                key: 'generic_git',
                label: 'Generic Git',
            ),
            new SourceProviderMetadata(
                key: 'bitbucket',
                label: 'Bitbucket',
                oauthProvider: 'bitbucket',
            ),
            new SourceProviderMetadata(
                key: 'codeberg',
                label: 'Codeberg',
                oauthProvider: 'codeberg',
            ),
        ];
    }

    public function get(string $key): ?SourceProviderMetadata
    {
        return array_find($this->all(), fn ($sourceProviderMetadatum) => $sourceProviderMetadatum->key === $key);
    }

    /**
     * @return list<SourceProviderMetadata>
     */
    public function loginOAuthBackedSourceProviders(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (SourceProviderMetadata $sourceProviderMetadata): bool => null !== $sourceProviderMetadata->oauthProvider
                && ! $sourceProviderMetadata->supportsSelfHostedInstances,
        ));
    }
}
