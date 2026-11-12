<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Bitbucket;

use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Provider\SourceProviderInterface;
use Packio\Source\Infrastructure\BitbucketRepositoryUrlParser;
use Packio\Source\Infrastructure\Provider\ProviderResourceFallbackFactory;
use Packio\Source\Infrastructure\Provider\SourceRegistrationFactory;
use Packio\Source\Infrastructure\Provider\WebhookSecretGenerator;

final readonly class BitbucketSourceProvider implements SourceProviderInterface
{
    public function __construct(
        private BitbucketRepositoryUrlParser $bitbucketRepositoryUrlParser,
        private BitbucketProviderResourceFetcherInterface $bitbucketProviderResourceFetcher,
        private ProviderResourceFallbackFactory $providerResourceFallbackFactory,
        private SourceRegistrationFactory $sourceRegistrationFactory,
        private WebhookSecretGenerator $webhookSecretGenerator,
    ) {}

    public function descriptor(): ProviderDescriptor
    {
        return new ProviderDescriptor(
            name: 'bitbucket',
            label: 'Bitbucket',
            supportsAuthorization: true,
            authorizationStrategies: ['oauth2', 'token'],
        );
    }

    /**
     * @return list<ProviderResourceData>
     */
    public function resources(ProviderAuthorization $providerAuthorization, ?string $accessToken = null): array
    {
        $resources = $this->bitbucketProviderResourceFetcher->fetchResources($accessToken);
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
        $parsed = $this->bitbucketRepositoryUrlParser->parse($sourceRegistrationData->url);

        return $this->sourceRegistrationFactory->registerGitSource(
            organizationId: $organizationId,
            provider: 'bitbucket',
            canonicalUrl: $parsed['normalized_url'],
            displayName: $parsed['workspace'] . '/' . $parsed['repository'],
            defaultBranch: $sourceRegistrationData->defaultBranch,
            visibility: $sourceRegistrationData->visibility,
            providerAuthorization: $providerAuthorization,
            providerResourceId: $parsed['workspace'] . '/' . $parsed['repository'],
            providerConfig: [
                'owner'          => $parsed['workspace'],
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
