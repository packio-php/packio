<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Mapper;

use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;

use function array_map;

final readonly class SourceDtoMapper
{
    public function __construct(
        private ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository,
        private SourceWebhookSecrets $sourceWebhookSecrets,
    ) {}

    public function map(Source $source): SourceDto
    {
        $authorization = '' !== $source->providerAuthorizationId()
            ? $this->providerAuthorizationRepository->findByIdInOrganization(
                $source->providerAuthorizationId(),
                $source->organizationId(),
            )
            : null;

        return $this->toDto($source, $authorization);
    }

    /**
     * @param list<Source> $sources
     *
     * @return list<SourceDto>
     */
    public function mapForOrganization(array $sources, string $organizationId): array
    {
        $authorizations = [];

        foreach ($this->providerAuthorizationRepository->findByOrganization($organizationId) as $providerAuthorization) {
            $authorizations[$providerAuthorization->getIdentifier()->toString()] = $providerAuthorization;
        }

        return array_map(
            fn (Source $source): SourceDto => $this->toDto($source, $authorizations[$source->providerAuthorizationId()] ?? null),
            $sources,
        );
    }

    private function toDto(Source $source, ?ProviderAuthorization $providerAuthorization): SourceDto
    {
        return new SourceDto(
            id: $source->getIdentifier()->toString(),
            provider: $source->provider(),
            type: $source->type(),
            displayName: $source->displayName(),
            canonicalUrl: $source->canonicalUrl(),
            url: $source->url(),
            owner: $source->owner(),
            repository: $source->repository(),
            defaultRef: $source->defaultRef(),
            organizationId: $source->organizationId(),
            visibility: $source->visibility(),
            active: $source->isActive(),
            lastSyncStatus: $source->lastSyncStatus(),
            lastSuccessfulSyncAt: $source->lastSuccessfulSyncAt()?->format(DATE_ATOM) ?? '',
            lastFailedSyncAt: $source->lastFailedSyncAt()?->format(DATE_ATOM) ?? '',
            lastSyncError: $source->lastSyncError(),
            archived: $source->isArchived(),
            archivedAt: $source->archivedAt()?->format(DATE_ATOM) ?? '',
            status: $source->status(),
            createdAt: $source->getCreatedAt()->format(DATE_ATOM),
            providerAuthorizationId: $source->providerAuthorizationId(),
            providerResourceId: $source->providerResourceId(),
            providerConfig: $this->sourceWebhookSecrets->decryptConfiguration($source->providerConfig()),
            authType: $providerAuthorization instanceof ProviderAuthorization
                && $providerAuthorization->provider() === $source->provider()
                    ? $providerAuthorization->authStrategy()
                    : '',
            credentialsConfigured: $providerAuthorization instanceof ProviderAuthorization
                && $providerAuthorization->provider() === $source->provider(),
            authorizationStatus: $providerAuthorization instanceof ProviderAuthorization
                && $providerAuthorization->provider() === $source->provider()
                    ? $providerAuthorization->status()
                    : '',
            keepLastReleases: $source->keepLastReleases(),
            updatedAt: $source->getUpdatedAt()?->format(DATE_ATOM) ?? '',
        );
    }
}
