<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Codeberg;

use Packio\Source\Api\Exception\ProviderAuthenticationException;
use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Provider\SourceProviderInterface;
use Packio\Source\Infrastructure\CodebergRepositoryUrlParser;
use Packio\Source\Infrastructure\Provider\SourceRegistrationFactory;
use Packio\Source\Infrastructure\Provider\WebhookSecretGenerator;

use function trim;

final readonly class CodebergSourceProvider implements SourceProviderInterface
{
    public function __construct(
        private CodebergRepositoryUrlParser $codebergRepositoryUrlParser,
        private CodebergProviderResourceFetcherInterface $codebergProviderResourceFetcher,
        private SourceRegistrationFactory $sourceRegistrationFactory,
        private WebhookSecretGenerator $webhookSecretGenerator,
    ) {}

    public function descriptor(): ProviderDescriptor
    {
        return new ProviderDescriptor(
            name: 'codeberg',
            label: 'Codeberg',
            supportsAuthorization: true,
            authorizationStrategies: ['oauth2', 'token'],
        );
    }

    /**
     * @return list<ProviderResourceData>
     */
    public function resources(ProviderAuthorization $providerAuthorization, ?string $accessToken = null): array
    {
        try {
            return $this->codebergProviderResourceFetcher->fetchResources($accessToken);
        } catch (ProviderAuthenticationException $exception) {
            if (null !== $providerAuthorization->sourceOAuthAccountId() && '' !== $providerAuthorization->sourceOAuthAccountId()) {
                throw $exception;
            }

            return [];
        }
    }

    public function registerSource(
        string $organizationId,
        SourceRegistrationData $sourceRegistrationData,
        ?ProviderAuthorization $providerAuthorization = null
    ): Source {
        $parsed     = $this->codebergRepositoryUrlParser->parse($sourceRegistrationData->url);
        $defaultRef = '' !== trim($sourceRegistrationData->defaultBranch)
            ? trim($sourceRegistrationData->defaultBranch)
            : 'main';

        return $this->sourceRegistrationFactory->registerGitSource(
            organizationId: $organizationId,
            provider: 'codeberg',
            canonicalUrl: $parsed['normalized_url'],
            displayName: $parsed['owner'] . '/' . $parsed['repository'],
            defaultBranch: $defaultRef,
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
