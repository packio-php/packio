<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Github;

use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Provider\SourceProviderInterface;
use Packio\Source\Infrastructure\GithubRepositoryUrlParser;
use Packio\Source\Infrastructure\Provider\ProviderResourceFallbackFactory;
use Packio\Source\Infrastructure\Provider\SourceRegistrationFactory;
use Packio\Source\Infrastructure\Provider\WebhookSecretGenerator;

final readonly class GithubSourceProvider implements SourceProviderInterface
{
    public function __construct(
        private GithubRepositoryUrlParser $githubRepositoryUrlParser,
        private GithubProviderResourceFetcherInterface $githubProviderResourceFetcher,
        private ProviderResourceFallbackFactory $providerResourceFallbackFactory,
        private SourceRegistrationFactory $sourceRegistrationFactory,
        private WebhookSecretGenerator $webhookSecretGenerator,
    ) {}

    public function descriptor(): ProviderDescriptor
    {
        return new ProviderDescriptor(
            name: 'github',
            label: 'GitHub',
            supportsAuthorization: true,
            authorizationStrategies: ['token'],
        );
    }

    /**
     * @return list<ProviderResourceData>
     */
    public function resources(ProviderAuthorization $providerAuthorization, ?string $accessToken = null): array
    {
        $resources = $this->githubProviderResourceFetcher->fetchResources(
            $accessToken,
        );
        if ([] !== $resources) {
            return $resources;
        }

        return $this->providerResourceFallbackFactory->fromAuthorizationConfiguration($providerAuthorization);
    }

    public function registerSource(
        string $organizationId,
        SourceRegistrationData $sourceRegistrationData,
        ?ProviderAuthorization $providerAuthorization = null
    ): Source {
        $parsed = $this->githubRepositoryUrlParser->parse($sourceRegistrationData->url);

        return $this->sourceRegistrationFactory->registerGitSource(
            organizationId: $organizationId,
            provider: 'github',
            canonicalUrl: $parsed['normalized_url'],
            displayName: $parsed['owner'] . '/' . $parsed['repository'],
            defaultBranch: $sourceRegistrationData->defaultBranch,
            visibility: $sourceRegistrationData->visibility,
            providerAuthorization: $providerAuthorization,
            providerResourceId: $parsed['owner'] . '/' . $parsed['repository'],
            providerConfig: [
                'owner'          => $parsed['owner'],
                'repository'     => $parsed['repository'],
                'webhook_secret' => $this->generateWebhookSecret(),
            ],
        );
    }

    public function generateWebhookSecret(): string
    {
        return $this->webhookSecretGenerator->generate();
    }
}
