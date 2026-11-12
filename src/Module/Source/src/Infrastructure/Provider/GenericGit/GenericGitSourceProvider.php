<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\GenericGit;

use Common\Infrastructure\HttpClient\RepositoryFilePath;
use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Provider\SourceProviderInterface;
use Packio\Source\Infrastructure\GenericGitRepositoryUrlParser;
use Packio\Source\Infrastructure\Provider\SourceRegistrationFactory;

use function trim;

final readonly class GenericGitSourceProvider implements SourceProviderInterface
{
    public function __construct(
        private GenericGitRepositoryUrlParser $genericGitRepositoryUrlParser,
        private SourceRegistrationFactory $sourceRegistrationFactory,
    ) {}

    public function descriptor(): ProviderDescriptor
    {
        return new ProviderDescriptor(
            name: 'generic_git',
            label: 'Generic Git',
            supportsAuthorization: true,
            authorizationStrategies: ['token'],
        );
    }

    /**
     * @return list<ProviderResourceData>
     */
    public function resources(ProviderAuthorization $providerAuthorization, ?string $accessToken = null): array
    {
        return [];
    }

    public function registerSource(
        string $organizationId,
        SourceRegistrationData $sourceRegistrationData,
        ?ProviderAuthorization $providerAuthorization = null
    ): Source {
        $parsed     = $this->genericGitRepositoryUrlParser->parse($sourceRegistrationData->url);
        $authScheme = trim((string) ($providerAuthorization?->configuration()['auth_scheme'] ?? 'bearer'));

        return $this->sourceRegistrationFactory->registerGitSource(
            organizationId: $organizationId,
            provider: 'generic_git',
            canonicalUrl: $parsed['normalized_url'],
            displayName: $parsed['display_name'],
            defaultBranch: $sourceRegistrationData->defaultBranch,
            visibility: $sourceRegistrationData->visibility,
            providerAuthorization: $providerAuthorization,
            providerResourceId: $parsed['repository'],
            providerConfig: [
                'repository'         => $parsed['repository'],
                'clone_url'          => $parsed['clone_url'],
                'composer_json_path' => RepositoryFilePath::normalize($sourceRegistrationData->composerJsonPath),
                'auth_scheme'        => $authScheme,
            ],
        );
    }

    public function generateWebhookSecret(): string
    {
        return '';
    }
}
