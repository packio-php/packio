<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;
use Packio\Source\Api\Exception\SourceProviderInstanceException;
use Packio\Source\Domain\Dto\SourceProviderInstanceCleanupResult;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceCleanupRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use RuntimeException;

use function array_map;
use function array_values;
use function count;

final readonly class SourceProviderInstanceCleanupRepository implements SourceProviderInstanceCleanupRepositoryInterface
{
    public function __construct(
        private SourceProviderInstanceRepository $sourceProviderInstanceRepository,
        private SourceRepositoryInterface $sourceRepository,
        private ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository,
        private SourceOAuthAccountRepositoryInterface $sourceOAuthAccountRepository,
    ) {}

    public function deleteStrict(string $organizationId, string $providerInstanceId): void
    {
        $this->sourceProviderInstanceRepository->transactionally(function(DatabaseInterface $database) use ($organizationId, $providerInstanceId): void {
            $this->lockProviderInstanceDependencies($database, $organizationId, $providerInstanceId);
            $sourceProviderInstance       = $this->requireLockedInstance($organizationId, $providerInstanceId);
            $authorizations               = $this->providerAuthorizationRepository->findLockedByProviderInstance($providerInstanceId, $organizationId);
            $sources                      = $this->lockedSourcesForInstanceAndAuthorizations(
                $organizationId,
                $providerInstanceId,
                $authorizations,
            );
            if ([] !== $sources || [] !== $authorizations) {
                throw SourceProviderInstanceException::inUse();
            }

            foreach ($this->sourceOAuthAccountRepository->findLockedByProviderInstance($providerInstanceId) as $sourceOAuthAccount) {
                $this->sourceOAuthAccountRepository->deleteAndFlush($sourceOAuthAccount);
            }

            $this->sourceProviderInstanceRepository->deleteAndFlush($sourceProviderInstance);
        });
    }

    public function deleteWithInactiveAuthorizations(
        string $organizationId,
        string $providerInstanceId
    ): SourceProviderInstanceCleanupResult {
        $result = $this->sourceProviderInstanceRepository->transactionally(function(DatabaseInterface $database) use ($organizationId, $providerInstanceId): SourceProviderInstanceCleanupResult {
            $this->lockProviderInstanceDependencies($database, $organizationId, $providerInstanceId);
            $sourceProviderInstance       = $this->requireLockedInstance($organizationId, $providerInstanceId);
            $authorizations               = $this->providerAuthorizationRepository->findLockedByProviderInstance($providerInstanceId, $organizationId);
            $sources                      = $this->lockedSourcesForInstanceAndAuthorizations(
                $organizationId,
                $providerInstanceId,
                $authorizations,
            );
            foreach ($authorizations as $authorization) {
                if ('inactive' !== $authorization->status()) {
                    throw SourceProviderInstanceException::inUse();
                }
            }

            if ([] !== $sources) {
                throw SourceProviderInstanceException::inUse();
            }

            foreach ($authorizations as $authorization) {
                $this->providerAuthorizationRepository->deleteAndFlush($authorization);
            }

            $sourceOAuthAccounts = $this->sourceOAuthAccountRepository->findLockedByProviderInstance($providerInstanceId);
            foreach ($sourceOAuthAccounts as $sourceOAuthAccount) {
                $this->sourceOAuthAccountRepository->deleteAndFlush($sourceOAuthAccount);
            }

            $this->sourceProviderInstanceRepository->deleteAndFlush($sourceProviderInstance);

            return new SourceProviderInstanceCleanupResult(count($authorizations), count($sourceOAuthAccounts));
        });

        if (! $result instanceof SourceProviderInstanceCleanupResult) {
            throw new RuntimeException('Source provider instance cleanup transaction did not return a result.');
        }

        return $result;
    }

    private function requireLockedInstance(string $organizationId, string $providerInstanceId): SourceProviderInstance
    {
        $instance = $this->sourceProviderInstanceRepository->findLockedByIdInOrganization($providerInstanceId, $organizationId);
        if (! $instance instanceof SourceProviderInstance) {
            throw SourceProviderInstanceException::notFound();
        }

        return $instance;
    }

    /**
     * @param list<ProviderAuthorization> $authorizations
     *
     * @return list<Source>
     */
    private function lockedSourcesForInstanceAndAuthorizations(
        string $organizationId,
        string $providerInstanceId,
        array $authorizations,
    ): array {
        $sources          = [];
        foreach ($this->sourceRepository->findLockedByProviderInstance($providerInstanceId, $organizationId) as $source) {
            $sources[$source->getIdentifier()->toString()] = $source;
        }
        $authorizationIds = array_map(
            static fn (ProviderAuthorization $providerAuthorization): string => $providerAuthorization->getIdentifier()->toString(),
            $authorizations,
        );
        foreach ($this->sourceRepository->findLockedByProviderAuthorizationIds($authorizationIds, $organizationId) as $source) {
            $sources[$source->getIdentifier()->toString()] = $source;
        }

        return array_values($sources);
    }

    private function lockProviderInstanceDependencies(
        DatabaseInterface $database,
        string $organizationId,
        string $providerInstanceId,
    ): void {
        $database->query(
            'SELECT pg_advisory_xact_lock(hashtext(?))',
            ['source-provider-instance-dependencies:' . $organizationId . ':' . $providerInstanceId],
        );
    }
}
