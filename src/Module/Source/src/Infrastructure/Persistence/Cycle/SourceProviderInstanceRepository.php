<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Persistence\Cycle;

use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\DatabaseInterface;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function iterator_to_array;

/**
 * @extends AbstractWriteRepository<SourceProviderInstance>
 */
final class SourceProviderInstanceRepository extends AbstractWriteRepository implements SourceProviderInstanceRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = SourceProviderInstance::class;

    public function save(SourceProviderInstance $sourceProviderInstance): SourceProviderInstance
    {
        $this->persistAndFlush($sourceProviderInstance);

        return $sourceProviderInstance;
    }

    public function findById(string $id): ?SourceProviderInstance
    {
        $entity = $this->select()->where([
            'uuid' => $id,
        ])->fetchOne();

        return $entity instanceof SourceProviderInstance ? $entity : null;
    }

    public function findByIdInOrganization(string $id, string $organizationId): ?SourceProviderInstance
    {
        $entity = $this->select()
            ->where([
                'uuid'           => $id,
                'organizationId' => $organizationId,
            ])
            ->fetchOne()
        ;

        return $entity instanceof SourceProviderInstance ? $entity : null;
    }

    public function findLockedByIdInOrganization(string $id, string $organizationId): ?SourceProviderInstance
    {
        $entity = $this->select()
            ->where([
                'uuid'           => $id,
                'organizationId' => $organizationId,
            ])
            ->forUpdate()
            ->fetchOne()
        ;

        return $entity instanceof SourceProviderInstance ? $entity : null;
    }

    public function findActiveByIdInOrganization(string $id, string $organizationId): ?SourceProviderInstance
    {
        $entity = $this->select()
            ->where([
                'uuid'           => $id,
                'organizationId' => $organizationId,
                'status'         => SourceProviderInstance::STATUS_ACTIVE,
            ])
            ->fetchOne()
        ;

        return $entity instanceof SourceProviderInstance ? $entity : null;
    }

    public function findByOrganizationProviderAndBaseUrl(
        string $organizationId,
        string $providerType,
        string $baseUrl
    ): ?SourceProviderInstance {
        $entity = $this->select()
            ->where([
                'organizationId' => $organizationId,
                'providerType'   => $providerType,
                'baseUrl'        => $baseUrl,
            ])
            ->fetchOne()
        ;

        return $entity instanceof SourceProviderInstance ? $entity : null;
    }

    public function findByOrganization(string $organizationId, ?string $providerType = null): array
    {
        $select = $this->select()->where([
            'organizationId' => $organizationId,
        ]);
        if (null !== $providerType) {
            $select->where([
                'providerType' => $providerType,
            ]);
        }

        return iterator_to_array(
            $select->orderBy([
                'providerType' => 'ASC',
                'label'        => 'ASC',
            ])->fetchAll(),
            false,
        );
    }

    /** @param callable(DatabaseInterface): mixed $callback */
    public function transactionally(callable $callback): mixed
    {
        return $this->database()->transaction($callback);
    }
}
