<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Persistence\Cycle;

use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\DatabaseInterface;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Exception\SourceProviderInstanceUnavailableException;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function iterator_to_array;

/**
 * @extends AbstractWriteRepository<ProviderAuthorization>
 */
final class ProviderAuthorizationRepository extends AbstractWriteRepository implements ProviderAuthorizationRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = ProviderAuthorization::class;

    /**
     * @throws RepositoryPersistException
     */
    public function save(ProviderAuthorization $providerAuthorization): ProviderAuthorization
    {
        $providerInstanceId = $providerAuthorization->providerInstanceId();
        if (null === $providerInstanceId || '' === $providerInstanceId) {
            $this->persistAndFlush($providerAuthorization);

            return $providerAuthorization;
        }

        $this->database()->transaction(function(DatabaseInterface $database) use ($providerAuthorization, $providerInstanceId): void {
            $this->lockProviderInstanceDependencies($database, $providerAuthorization->organizationId(), $providerInstanceId);
            $rows = $database->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
                ->selectQuery($database->getPrefix())
                ->from('source_provider_instances')
                ->columns('uuid')
                ->where('uuid', $providerInstanceId)
                ->where('organization_id', $providerAuthorization->organizationId())
                ->forUpdate()
                ->limit(1)
                ->fetchAll()
            ;
            if ([] === $rows) {
                throw new SourceProviderInstanceUnavailableException('Source provider instance is not available.');
            }

            $this->persistAndFlush($providerAuthorization);
        });

        return $providerAuthorization;
    }

    public function findById(string $id): ?ProviderAuthorization
    {
        $entity = $this->select()->where([
            'uuid' => $id,
        ])->fetchOne();

        return $entity instanceof ProviderAuthorization ? $entity : null;
    }

    public function findByIdInOrganization(string $id, string $organizationId): ?ProviderAuthorization
    {
        $entity = $this->select()
            ->where([
                'uuid'           => $id,
                'organizationId' => $organizationId,
            ])
            ->fetchOne()
        ;

        return $entity instanceof ProviderAuthorization ? $entity : null;
    }

    public function findByOrganization(string $organizationId, ?string $provider = null): array
    {
        $select = $this->select()->where([
            'organizationId' => $organizationId,
        ]);
        if (null !== $provider) {
            $select->where([
                'provider' => $provider,
            ]);
        }

        return iterator_to_array(
            $select->orderBy([
                'createdAt' => 'DESC',
            ])->fetchAll(),
            false,
        );
    }

    public function findActiveBySourceOAuthAccountId(string $sourceOAuthAccountId, ?string $organizationId = null): array
    {
        $select = $this->select()
            ->where([
                'sourceOAuthAccountId' => $sourceOAuthAccountId,
                'status'               => 'active',
            ])
        ;

        if (null !== $organizationId && '' !== $organizationId) {
            $select->where([
                'organizationId' => $organizationId,
            ]);
        }

        return iterator_to_array(
            $select
                ->orderBy([
                    'createdAt' => 'DESC',
                ])
                ->fetchAll(),
            false,
        );
    }

    public function findByProviderInstance(string $providerInstanceId, string $organizationId): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'organizationId'     => $organizationId,
                    'providerInstanceId' => $providerInstanceId,
                ])
                ->orderBy([
                    'createdAt' => 'DESC',
                ])
                ->fetchAll(),
            false,
        );
    }

    public function findLockedByProviderInstance(string $providerInstanceId, string $organizationId): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'organizationId'     => $organizationId,
                    'providerInstanceId' => $providerInstanceId,
                ])
                ->orderBy([
                    'createdAt' => 'DESC',
                ])
                ->forUpdate()
                ->fetchAll(),
            false,
        );
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
