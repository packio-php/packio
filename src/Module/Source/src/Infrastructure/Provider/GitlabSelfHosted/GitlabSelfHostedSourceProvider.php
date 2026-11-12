<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\GitlabSelfHosted;

use InvalidArgumentException;
use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Provider\SourceProviderInterface;
use Packio\Source\Infrastructure\GitlabSelfHostedRepositoryUrlParser;
use Packio\Source\Infrastructure\Provider\ProviderResourceFallbackFactory;
use Packio\Source\Infrastructure\Provider\SourceRegistrationFactory;
use Packio\Source\Infrastructure\Provider\WebhookSecretGenerator;

use function trim;

final readonly class GitlabSelfHostedSourceProvider implements SourceProviderInterface
{
    public function __construct(
        private GitlabSelfHostedRepositoryUrlParser $gitlabSelfHostedRepositoryUrlParser,
        private GitlabSelfHostedProviderResourceFetcherInterface $gitlabSelfHostedProviderResourceFetcher,
        private ProviderResourceFallbackFactory $providerResourceFallbackFactory,
        private SourceRegistrationFactory $sourceRegistrationFactory,
        private WebhookSecretGenerator $webhookSecretGenerator,
    ) {}

    public function descriptor(): ProviderDescriptor
    {
        return new ProviderDescriptor(
            name: 'gitlab_self_hosted',
            label: 'GitLab Self-Hosted',
            supportsAuthorization: true,
            authorizationStrategies: ['oauth2', 'token'],
        );
    }

    /**
     * @return list<ProviderResourceData>
     */
    public function resources(ProviderAuthorization $providerAuthorization, ?string $accessToken = null): array
    {
        $config  = $providerAuthorization->configuration();
        $baseUrl = trim((string) ($config['base_url'] ?? ''));

        if ('' === $baseUrl) {
            return [];
        }

        $resources = $this->gitlabSelfHostedProviderResourceFetcher->fetchResources(
            $baseUrl,
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
        $parsed               = $this->gitlabSelfHostedRepositoryUrlParser->parse($sourceRegistrationData->url);
        $authorizationBaseUrl = trim((string) ($providerAuthorization?->configuration()['base_url'] ?? ''));
        if (
            null !== $providerAuthorization?->providerInstanceId()
            && '' !== $authorizationBaseUrl
            && $authorizationBaseUrl !== $parsed['base_url']
        ) {
            throw new InvalidArgumentException('GitLab repository URL does not match the selected provider instance.');
        }

        $source = $this->sourceRegistrationFactory->registerGitSource(
            organizationId: $organizationId,
            provider: 'gitlab_self_hosted',
            canonicalUrl: $parsed['normalized_url'],
            displayName: $parsed['namespace'] . '/' . $parsed['repository'],
            defaultBranch: $sourceRegistrationData->defaultBranch,
            visibility: $sourceRegistrationData->visibility,
            providerAuthorization: $providerAuthorization,
            providerResourceId: $parsed['namespace'] . '/' . $parsed['repository'],
            providerConfig: [
                'owner'          => $parsed['namespace'],
                'repository'     => $parsed['repository'],
                'base_url'       => $parsed['base_url'],
                ...($providerAuthorization?->providerInstanceId() ? [
                    'provider_instance_id' => $providerAuthorization->providerInstanceId(),
                ] : []),
                'webhook_secret' => $this->generateWebhookSecret(),
            ],
        );
        $source->assignProviderInstance($providerAuthorization?->providerInstanceId());

        return $source;
    }

    public function generateWebhookSecret(): string
    {
        return $this->webhookSecretGenerator->generate();
    }
}
