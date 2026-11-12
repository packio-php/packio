<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Persistence\Cycle;

use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Injection\Fragment;
use Packio\Organization\Domain\Entity\OrganizationMember;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;
use Throwable;

use function ceil;
use function count;
use function iterator_to_array;
use function max;
use function min;

/**
 * @extends AbstractWriteRepository<OrganizationMember>
 */
final class OrganizationMemberRepository extends AbstractWriteRepository implements OrganizationMemberRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = OrganizationMember::class;

    /**
     * @throws RepositoryPersistException
     */
    public function save(OrganizationMember $organizationMember): OrganizationMember
    {
        $this->persistAndFlush($organizationMember);

        return $organizationMember;
    }

    /**
     * @throws Throwable
     */
    public function demoteOwnerToMemberWhenAnotherOwnerExists(string $organizationId, string $memberId): bool
    {
        return $this->database()->transaction(
            function(DatabaseInterface $database) use ($organizationId, $memberId): bool {
                $owners = $database
                    ->getDriver()
                    ->getQueryBuilder()
                    ->selectQuery($database->getPrefix())
                    ->from('organization_members')
                    ->columns('uuid')
                    ->where('organization_id', $organizationId)
                    ->where('role', 'owner')
                    ->forUpdate()
                    ->fetchAll()
                ;

                if (count($owners) < 2) {
                    return false;
                }

                return 1 === $database
                    ->update('organization_members')
                    ->values([
                        'role'       => 'member',
                        'updated_at' => new Fragment('CURRENT_TIMESTAMP'),
                    ])
                    ->where('uuid', $memberId)
                    ->where('organization_id', $organizationId)
                    ->where('role', 'owner')
                    ->run()
                ;
            },
        );
    }

    /**
     * @throws Throwable
     */
    public function deleteOwnerWhenAnotherOwnerExists(string $organizationId, string $memberId): bool
    {
        $membership = $this->findById($memberId);

        $deleted = $this->database()->transaction(
            function(DatabaseInterface $database) use ($organizationId, $memberId): bool {
                $owners = $database
                    ->getDriver()
                    ->getQueryBuilder()
                    ->selectQuery($database->getPrefix())
                    ->from('organization_members')
                    ->columns('uuid')
                    ->where('organization_id', $organizationId)
                    ->where('role', 'owner')
                    ->forUpdate()
                    ->fetchAll()
                ;

                if (count($owners) < 2) {
                    return false;
                }

                return 1 === $database
                    ->delete('organization_members')
                    ->where('uuid', $memberId)
                    ->where('organization_id', $organizationId)
                    ->where('role', 'owner')
                    ->run()
                ;
            },
        );

        // Raw SQL bypasses the ORM: drop the stale entity from the heap.
        if ($deleted && $membership instanceof OrganizationMember) {
            $this->orm->getHeap()->detach($membership);
        }

        return $deleted;
    }

    public function findById(string $id): ?OrganizationMember
    {
        return $this->select()
            ->where([
                'uuid' => $id,
            ])
            ->fetchOne()
        ;
    }

    public function findByOrganizationAndUser(string $organizationId, string $userId): ?OrganizationMember
    {
        return $this->select()
            ->where([
                'organizationId' => $organizationId,
                'userId'         => $userId,
            ])
            ->fetchOne()
        ;
    }

    public function findByOrganization(string $organizationId): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'organizationId' => $organizationId,
                ])
                ->orderBy([
                    'createdAt' => 'ASC',
                ])
                ->fetchAll(),
            false,
        );
    }

    public function findPaginatedByOrganization(string $organizationId, PaginationCriteria $paginationCriteria): array
    {
        $select = $this->select()
            ->where([
                'organizationId' => $organizationId,
            ])
            ->orderBy([
                'createdAt' => 'ASC',
                'uuid'      => 'ASC',
            ])
        ;

        $total       = $select->count('*');
        $lastPage    = max(1, (int) ceil($total / $paginationCriteria->perPage));
        $currentPage = min($paginationCriteria->page, $lastPage);

        return [
            'items'       => iterator_to_array(
                $select
                    ->offset(($currentPage - 1) * $paginationCriteria->perPage)
                    ->limit($paginationCriteria->perPage)
                    ->fetchAll(),
                false,
            ),
            'total'       => $total,
            'currentPage' => $currentPage,
            'perPage'     => $paginationCriteria->perPage,
        ];
    }

    public function countByOrganization(string $organizationId): int
    {
        return $this->select()->where('organizationId', $organizationId)->count('*');
    }

    public function findByUserId(string $userId): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'userId' => $userId,
                ])
                ->orderBy([
                    'createdAt' => 'DESC',
                ])
                ->fetchAll(),
            false,
        );
    }
}
