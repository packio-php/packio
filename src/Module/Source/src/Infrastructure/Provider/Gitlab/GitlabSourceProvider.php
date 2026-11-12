<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Provider\Gitlab;

use Common\Branding\BrandingConfig;
use Common\Infrastructure\HttpClient\Exception\UpstreamHttpException;
use Common\Infrastructure\HttpClient\UpstreamHttpClient;
use Common\Security\StoredSecretResolver;
use Packio\Source\Api\Exception\ProviderAuthenticationException;
use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Dto\SourceRegistrationData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Provider\SourceProviderInterface;
use Packio\Source\Infrastructure\GitlabRepositoryUrlParser;
use Packio\Source\Infrastructure\Provider\ProviderResourceFallbackFactory;
use Packio\Source\Infrastructure\Provider\SourceRegistrationFactory;
use Packio\Source\Infrastructure\Provider\WebhookSecretGenerator;

use function is_array;
use function is_string;
use function rawurlencode;
use function trim;

final readonly class GitlabSourceProvider implements SourceProviderInterface
{
    public function __construct(
        private GitlabRepositoryUrlParser $gitlabRepositoryUrlParser,
        private GitlabProviderResourceFetcherInterface $gitlabProviderResourceFetcher,
        private StoredSecretResolver $storedSecretResolver,
        private BrandingConfig $brandingConfig,
        private UpstreamHttpClient $upstreamHttpClient,
        private ProviderResourceFallbackFactory $providerResourceFallbackFactory,
        private SourceRegistrationFactory $sourceRegistrationFactory,
        private WebhookSecretGenerator $webhookSecretGenerator,
    ) {}

    public function descriptor(): ProviderDescriptor
    {
        return new ProviderDescriptor(
            name: 'gitlab',
            label: 'GitLab',
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
            $resources = $this->gitlabProviderResourceFetcher->fetchResources(
                $accessToken,
            );
        } catch (ProviderAuthenticationException $exception) {
            if (null !== $providerAuthorization->sourceOAuthAccountId() && '' !== $providerAuthorization->sourceOAuthAccountId()) {
                throw $exception;
            }

            $resources = [];
        }
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
        $parsed = $this->gitlabRepositoryUrlParser->parse($sourceRegistrationData->url);

        $defaultRef = '' !== trim($sourceRegistrationData->defaultBranch)
            ? trim($sourceRegistrationData->defaultBranch)
            : $this->fetchDefaultBranch($providerAuthorization, $parsed);

        return $this->sourceRegistrationFactory->registerGitSource(
            organizationId: $organizationId,
            provider: 'gitlab',
            canonicalUrl: $parsed['normalized_url'],
            displayName: $parsed['namespace'] . '/' . $parsed['repository'],
            defaultBranch: $defaultRef,
            visibility: $sourceRegistrationData->visibility,
            providerAuthorization: $providerAuthorization,
            providerResourceId: $parsed['namespace'] . '/' . $parsed['repository'],
            providerConfig: [
                'owner'          => $parsed['namespace'],
                'repository'     => $parsed['repository'],
                'webhook_secret' => $this->generateWebhookSecret(),
            ],
        );
    }

    public function generateWebhookSecret(): string
    {
        return $this->webhookSecretGenerator->generate();
    }

    /**
     * @param array{namespace: string, repository: string, normalized_url: string} $parsed
     */
    private function fetchDefaultBranch(?ProviderAuthorization $providerAuthorization, array $parsed): string
    {
        $accessToken = '';
        if ($providerAuthorization instanceof ProviderAuthorization) {
            $storedToken = $providerAuthorization->secret('token');
            $decrypted   = $this->storedSecretResolver->decrypt($storedToken);
            if (is_string($decrypted) && '' !== $decrypted) {
                $accessToken = $decrypted;
            }
        }

        if ('' === $accessToken) {
            return 'main';
        }

        $encodedNamespace = rawurlencode($parsed['namespace']);
        $encodedRepo      = rawurlencode($parsed['repository']);
        $url              = "https://gitlab.com/api/v4/projects/{$encodedNamespace}%2F{$encodedRepo}";

        $headers = [
            'Accept'        => 'application/json',
            'User-Agent'    => $this->brandingConfig->httpUserAgent(),
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        try {
            $response = $this->upstreamHttpClient->request('GET', $url, $headers, timeout: 10);
        } catch (UpstreamHttpException) {
            return 'main';
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            return 'main';
        }

        $defaultBranch = $decoded['default_branch'] ?? null;
        if (is_string($defaultBranch) && '' !== trim($defaultBranch)) {
            return trim($defaultBranch);
        }

        return 'main';
    }
}
