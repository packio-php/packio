<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Persistence\Cycle;

use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Injection\Fragment;
use Override;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Repository\OrganizationRepositoryInterface;
use Packio\Organization\Domain\Value\OrganizationDeletionInitiation;
use Packio\Organization\Domain\Value\OrganizationDeletionInitiationOutcome;
use Ramsey\Uuid\Uuid;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function is_array;
use function is_string;
use function iterator_to_array;

/**
 * @extends AbstractWriteRepository<Organization>
 */
final class OrganizationRepository extends AbstractWriteRepository implements OrganizationRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = Organization::class;

    public function countAll(): int
    {
        return $this->select()->count('*');
    }

    /**
     * @throws RepositoryPersistException
     */
    public function save(Organization $organization): Organization
    {
        $this->persistAndFlush($organization);

        return $organization;
    }

    public function findById(string $id): ?Organization
    {
        return $this->select()
            ->where([
                'uuid' => $id,
            ])
            ->fetchOne()
        ;
    }

    public function initiateDeletion(string $id): OrganizationDeletionInitiation
    {
        $organizationDeletionInitiation = $this->database()->transaction(function(DatabaseInterface $database) use ($id): OrganizationDeletionInitiation {
            $rows = $database->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
                ->selectQuery($database->getPrefix())
                ->from('organizations')
                ->columns('uuid', 'active', 'status')
                ->where('uuid', $id)
                ->forUpdate()
                ->limit(1)
                ->fetchAll()
            ;
            $row = $rows[0] ?? null;

            if (! is_array($row)) {
                return new OrganizationDeletionInitiation(OrganizationDeletionInitiationOutcome::NotFound);
            }

            if (Organization::STATUS_DELETING === (string) ($row['status'] ?? '')) {
                return new OrganizationDeletionInitiation(OrganizationDeletionInitiationOutcome::AlreadyDeleting);
            }

            $previousActive = (bool) ($row['active'] ?? false);
            $deletionRunId  = Uuid::uuid7()->toString();

            $database->update('organizations')->values([
                'status'          => Organization::STATUS_DELETING,
                'active'          => false,
                'deletion_run_id' => $deletionRunId,
                'updated_at'      => new Fragment('CURRENT_TIMESTAMP'),
            ])->where('uuid', $id)
                ->run()
            ;

            return new OrganizationDeletionInitiation(
                OrganizationDeletionInitiationOutcome::Initiated,
                $previousActive,
                $deletionRunId,
            );
        });

        if (OrganizationDeletionInitiationOutcome::Initiated === $organizationDeletionInitiation->outcome) {
            $this->detachFromHeap($id);
        }

        return $organizationDeletionInitiation;
    }

    public function revertInitiation(string $id, bool $previousActive, string $deletionRunId): void
    {
        $this->database()->update('organizations')->values([
            'status'          => Organization::STATUS_ACTIVE,
            'active'          => $previousActive,
            'deletion_run_id' => null,
            'updated_at'      => new Fragment('CURRENT_TIMESTAMP'),
        ])->where('uuid', $id)
            ->where('status', Organization::STATUS_DELETING)
            ->where('deletion_run_id', $deletionRunId)
            ->run()
        ;

        $this->detachFromHeap($id);
    }

    public function isDeletionRunCurrent(string $id, string $deletionRunId): bool
    {
        $rows = $this->database()->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
            ->selectQuery($this->database()->getPrefix())
            ->from('organizations')
            ->columns('uuid')
            ->where('uuid', $id)
            ->where('status', Organization::STATUS_DELETING)
            ->where('deletion_run_id', $deletionRunId)
            ->limit(1)
            ->fetchAll()
        ;

        return [] !== $rows;
    }

    public function ensureDeletionRun(string $id): ?string
    {
        $deletionRunId = $this->database()->transaction(function(DatabaseInterface $database) use ($id): ?string {
            $rows = $database->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
                ->selectQuery($database->getPrefix())
                ->from('organizations')
                ->columns('status', 'deletion_run_id')
                ->where('uuid', $id)
                ->forUpdate()
                ->limit(1)
                ->fetchAll()
            ;
            $row = $rows[0] ?? null;
            if (! is_array($row) || Organization::STATUS_DELETING !== (string) ($row['status'] ?? '')) {
                return null;
            }

            $currentDeletionRunId = $row['deletion_run_id'] ?? null;
            if (is_string($currentDeletionRunId) && '' !== $currentDeletionRunId) {
                return $currentDeletionRunId;
            }

            $newDeletionRunId = Uuid::uuid7()->toString();
            $database->update('organizations')->values([
                'deletion_run_id' => $newDeletionRunId,
                'updated_at'      => new Fragment('CURRENT_TIMESTAMP'),
            ])->where('uuid', $id)
                ->where('status', Organization::STATUS_DELETING)
                ->where('deletion_run_id', null)
                ->run()
            ;

            return $newDeletionRunId;
        });

        $this->detachFromHeap($id);

        return $deletionRunId;
    }

    public function findBySlug(string $slug): ?Organization
    {
        return $this->select()
            ->where([
                'slug' => $slug,
            ])
            ->fetchOne()
        ;
    }

    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return iterator_to_array(
            $this->select()
                ->where([
                    'uuid' => [
                        'in' => $ids,
                    ],
                ])
                ->fetchAll(),
            false,
        );
    }

    /**
     * @param array<string, mixed>        $scope
     * @param array<string, 'ASC'|'DESC'> $orderBy
     *
     * @return list<Organization>
     */
    #[Override]
    public function findAll(array $scope = [], array $orderBy = []): array
    {
        /** @var non-empty-array<non-empty-string, non-empty-string> $sort */
        $sort = [] === $orderBy ? [
            'createdAt' => 'DESC',
        ] : $orderBy;

        return iterator_to_array(
            $this->select()->where($scope)->orderBy($sort)->fetchAll(),
            false,
        );
    }

    #[Override]
    public function existsWithNameKey(string $nameKey, ?string $exceptOrganizationId = null): bool
    {
        $select = $this->select()->where(new Fragment('LOWER(name)'), '=', new Fragment('LOWER(?)', $nameKey));

        if (null !== $exceptOrganizationId) {
            $select->where('uuid', '!=', $exceptOrganizationId);
        }

        return $select->fetchOne() instanceof Organization;
    }

    /**
     * The conditional initiation update bypasses the ORM change set, so a
     * cached heap entity would still carry the previous status. Detach it so
     * the next read hydrates fresh data.
     */
    private function detachFromHeap(string $id): void
    {
        $organization = $this->findById($id);
        if ($organization instanceof Organization) {
            $this->orm->getHeap()->detach($organization);
        }
    }
}
