<?php

declare(strict_types=1);

namespace Packio\Source\Application\Query;

use Packio\Source\Api\Exception\ProviderAuthenticationException;
use Packio\Source\Api\Exception\ProviderCallException;
use Packio\Source\Api\Exception\ProviderFailure;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Service\SecretResolverInterface;
use Packio\Source\Application\Service\SourceAuthorizationInvalidationService;
use Packio\Source\Application\Service\SourceOAuthTokenResolver;
use Packio\Source\Domain\Dto\ProviderDescriptor;
use Packio\Source\Domain\Dto\ProviderResourceData;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Exception\SourceTokenMissingException;
use Packio\Source\Domain\Exception\SourceTokenRefreshFailedException;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use RuntimeException;

use function array_map;
use function count;
use function is_string;
use function strlen;
use function substr;
use function trim;

final readonly class SourceQueryService
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository,
        private SourceProviderRegistryInterface $sourceProviderRegistry,
        private ProviderAuthorizationSecrets $providerAuthorizationSecrets,
        private SecretResolverInterface $secretResolver,
        private SourceAuthorizationInvalidationService $sourceAuthorizationInvalidationService,
        private SourceOAuthTokenResolver $sourceOAuthTokenResolver,
    ) {}

    /**
     * @return list<Source>
     */
    public function allSources(): array
    {
        return $this->sourceRepository->findAll();
    }

    /**
     * @return list<Source>
     */
    public function sourcesForOrganization(string $organizationId): array
    {
        return $this->sourceRepository->findByOrganization($organizationId);
    }

    public function countSources(): int
    {
        return $this->sourceRepository->countAll();
    }

    public function countForOrganization(string $organizationId): int
    {
        return $this->sourceRepository->countForOrganization($organizationId);
    }

    public function findSource(string $id): ?Source
    {
        $entity = $this->sourceRepository->findById($id);

        return $entity instanceof Source ? $entity : null;
    }

    public function findSourceByUrl(string $url, ?string $organizationId = null): ?Source
    {
        $entity = $this->sourceRepository->findByUrl($url, $organizationId);

        return $entity instanceof Source ? $entity : null;
    }

    /**
     * @return list<ProviderDescriptor>
     */
    public function availableProviders(): array
    {
        return array_map(
            static fn ($provider): ProviderDescriptor => $provider->descriptor(),
            $this->sourceProviderRegistry->providers(),
        );
    }

    /**
     * @return list<ProviderAuthorization>
     */
    public function providerAuthorizations(string $organizationId, ?string $provider = null): array
    {
        return $this->providerAuthorizationRepository->findByOrganization(
            $organizationId,
            $provider,
        );
    }

    public function findProviderAuthorizationInOrganization(string $id, string $organizationId): ?ProviderAuthorization
    {
        return $this->providerAuthorizationRepository->findByIdInOrganization($id, $organizationId);
    }

    public function findProviderAuthorization(string $id): ?ProviderAuthorization
    {
        $authorization = $this->providerAuthorizationRepository->findById($id);

        return $authorization instanceof ProviderAuthorization ? $authorization : null;
    }

    /**
     * @return list<ProviderResourceData>
     */
    public function providerResources(string $provider, string $authorizationId, string $organizationId): array
    {
        $resolvedOrganizationId = trim($organizationId);
        if ('' === $resolvedOrganizationId) {
            throw new RuntimeException('Organization is required to query provider resources.');
        }

        $authorization = $this->providerAuthorizationRepository->findByIdInOrganization(
            $authorizationId,
            $resolvedOrganizationId,
        );
        if (
            ! $authorization instanceof ProviderAuthorization
            || $authorization->provider() !== $provider
        ) {
            return [];
        }

        $sourceOAuthAccountId = $authorization->sourceOAuthAccountId();

        try {
            if (null !== $sourceOAuthAccountId && '' !== $sourceOAuthAccountId) {
                $resolved    = $this->sourceOAuthTokenResolver->resolveForProviderAuthorization($authorization);
                $accessToken = $resolved['access_token'];
            } else {
                $accessToken = $this->providerAuthorizationSecrets->decryptToken($authorization);
            }
        } catch (
            SourceTokenMissingException|SourceTokenRefreshFailedException|SourceTokenRefreshUnavailableException $exception
        ) {
            if ($exception instanceof SourceTokenRefreshFailedException && ! $exception->requiresReauthorization) {
                throw new ProviderCallException(ProviderFailure::Unavailable, $exception);
            }

            $this->sourceAuthorizationInvalidationService->markInvalid($authorization);

            throw new ProviderAuthenticationException('Provider authorization is invalid or expired. Please reconnect.', 0, $exception);
        }

        try {
            return $this->sourceProviderRegistry->provider($provider)->resources(
                $authorization,
                $accessToken,
            );
        } catch (ProviderAuthenticationException $exception) {
            $this->sourceAuthorizationInvalidationService->markInvalid($authorization);

            throw new ProviderAuthenticationException('Provider authorization is invalid or expired. Please reconnect.', 0, $exception);
        }
    }

    /**
     * @return array{
     *     has_sources: bool,
     *     source_count: int,
     *     sources: list<array{id: string, name: string, has_packages: bool}>,
     *     other_authorizations: list<array{id: string, provider: string, auth_strategy: string, masked_value: string}>
     * }
     */
    public function checkAuthorizationDependencies(string $id, string $organizationId): array
    {
        $authorization       = $this->providerAuthorizationRepository->findByIdInOrganization($id, $organizationId);
        $sources             = $this->sourceRepository->findByProviderAuthorizationId($id, $organizationId);
        $otherAuthorizations = $this->providerAuthorizationRepository->findByOrganization($organizationId);

        $sourcesPayload = [];
        foreach ($sources as $source) {
            $sourcesPayload[] = [
                'id'           => $source->getIdentifier()->toString(),
                'name'         => '' !== $source->displayName()
                    ? $source->displayName()
                    : $source->owner() . '/' . $source->repository(),
                'has_packages' => false,
            ];
        }

        $provider = $authorization instanceof ProviderAuthorization
            ? $authorization->provider()
            : '';

        $otherPayload = [];
        foreach ($otherAuthorizations as $otherAuthorization) {
            if ($otherAuthorization->getIdentifier()->toString() === $id) {
                continue;
            }

            if ('' !== $provider && $otherAuthorization->provider() !== $provider) {
                continue;
            }

            try {
                $token = $this->secretResolver->resolveAuthorizationSecret($otherAuthorization->getIdentifier()->toString());
            } catch (
                SourceTokenMissingException|SourceTokenRefreshFailedException|SourceTokenRefreshUnavailableException
            ) {
                $token = null;
            }

            $maskedValue = '';
            if (is_string($token) && strlen($token) >= 4) {
                $maskedValue = '****' . substr($token, -4);
            }

            $otherPayload[] = [
                'id'            => $otherAuthorization->getIdentifier()->toString(),
                'provider'      => $otherAuthorization->provider(),
                'auth_strategy' => $otherAuthorization->authStrategy(),
                'masked_value'  => $maskedValue,
            ];
        }

        return [
            'has_sources'          => count($sources) > 0,
            'source_count'         => count($sources),
            'sources'              => $sourcesPayload,
            'other_authorizations' => $otherPayload,
        ];
    }
}
