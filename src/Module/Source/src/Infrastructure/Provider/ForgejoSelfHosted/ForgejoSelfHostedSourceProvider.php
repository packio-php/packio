<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\ForgejoSelfHosted;

use InvalidArgumentException;
use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Provider\SourceProviderInterface;
use Packio\Source\Infrastructure\CodebergRepositoryUrlParser;
use Packio\Source\Infrastructure\Provider\Codeberg\CodebergProviderResourceFetcherInterface;
use Packio\Source\Infrastructure\Provider\ProviderResourceFallbackFactory;
use Packio\Source\Infrastructure\Provider\SourceRegistrationFactory;
use Packio\Source\Infrastructure\Provider\WebhookSecretGenerator;

use function trim;

final readonly class ForgejoSelfHostedSourceProvider implements SourceProviderInterface
{
    public function __construct(
        private CodebergRepositoryUrlParser $codebergRepositoryUrlParser,
        private CodebergProviderResourceFetcherInterface $codebergProviderResourceFetcher,
        private ProviderResourceFallbackFactory $providerResourceFallbackFactory,
        private SourceRegistrationFactory $sourceRegistrationFactory,
        private WebhookSecretGenerator $webhookSecretGenerator,
    ) {}

    public function descriptor(): ProviderDescriptor
    {
        return new ProviderDescriptor(
            name: 'forgejo_self_hosted',
            label: 'Forgejo Self-Hosted',
            supportsAuthorization: true,
            authorizationStrategies: ['oauth2', 'token'],
        );
    }

    /**
     * @return list<ProviderResourceData>
     */
    public function resources(ProviderAuthorization $providerAuthorization, ?string $accessToken = null): array
    {
        $baseUrl = trim((string) ($providerAuthorization->configuration()['base_url'] ?? ''));

        if ('' === $baseUrl) {
            return [];
        }

        $resources = $this->codebergProviderResourceFetcher->fetchResources($accessToken, $baseUrl, 'forgejo_self_hosted');
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
        $baseUrl = trim((string) ($providerAuthorization?->configuration()['base_url'] ?? ''));
        if ('' === $baseUrl) {
            throw new InvalidArgumentException('A Forgejo provider instance is required to register this repository.');
        }

        try {
            $parsed = $this->codebergRepositoryUrlParser->parse($sourceRegistrationData->url, $baseUrl);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException('Forgejo repository URL does not match the selected provider instance.', 0, $exception);
        }

        $providerInstanceId = $providerAuthorization?->providerInstanceId();
        $source             = $this->sourceRegistrationFactory->registerGitSource(
            organizationId: $organizationId,
            provider: 'forgejo_self_hosted',
            canonicalUrl: $parsed['normalized_url'],
            displayName: $parsed['owner'] . '/' . $parsed['repository'],
            defaultBranch: $sourceRegistrationData->defaultBranch,
            visibility: $sourceRegistrationData->visibility,
            providerAuthorization: $providerAuthorization,
            providerResourceId: $parsed['owner'] . '/' . $parsed['repository'],
            providerConfig: [
                'owner'          => $parsed['owner'],
                'repository'     => $parsed['repository'],
                'base_url'       => $parsed['base_url'],
                ...($providerInstanceId ? [
                    'provider_instance_id' => $providerInstanceId,
                ] : []),
                'webhook_secret' => $this->generateWebhookSecret(),
            ],
        );
        $source->assignProviderInstance($providerInstanceId);

        return $source;
    }

    public function generateWebhookSecret(): string
    {
        return $this->webhookSecretGenerator->generate();
    }
}
