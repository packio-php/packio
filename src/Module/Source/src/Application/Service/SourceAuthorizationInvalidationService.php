<?php

declare(strict_types=1);

namespace Packio\Source\Application\Service;

use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;

final readonly class SourceAuthorizationInvalidationService
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository,
        private SourceOAuthService $sourceOAuthService,
    ) {}

    public function markInvalidForSource(string $sourceId): void
    {
        $source = $this->sourceRepository->findById($sourceId);
        if (
            ! $source instanceof Source
            || '' === $source->providerAuthorizationId()
        ) {
            return;
        }

        $authorization = $this->providerAuthorizationRepository->findByIdInOrganization(
            $source->providerAuthorizationId(),
            $source->organizationId(),
        );
        if (
            ! $authorization instanceof ProviderAuthorization
            || $authorization->provider() !== $source->provider()
        ) {
            return;
        }

        $this->markInvalid($authorization);
    }

    public function markInvalid(ProviderAuthorization $providerAuthorization): void
    {
        $providerAuthorization->updateStatus('inactive');
        $this->providerAuthorizationRepository->save($providerAuthorization);

        $sourceOAuthAccountId = $providerAuthorization->sourceOAuthAccountId();
        if (null !== $sourceOAuthAccountId && '' !== $sourceOAuthAccountId) {
            $this->sourceOAuthService->invalidateSourceOAuth($sourceOAuthAccountId);
        }
    }
}
