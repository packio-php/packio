<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Persistence\Cycle;

use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Injection\Fragment;
use Override;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Exception\SourceProviderInstanceUnavailableException;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Source\Domain\Value\SourceDeletionExecutionLock;
use Packio\Source\Domain\Value\SourceDeletionInitiation;
use Packio\Source\Domain\Value\SourceDeletionInitiationOutcome;
use Ramsey\Uuid\Uuid;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function is_array;
use function is_string;
use function iterator_to_array;

/**
 * @extends AbstractWriteRepository<Source>
 */
final class SourceRepository extends AbstractWriteRepository implements SourceRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = Source::class;

    public function save(Source $source): Source
    {
        $providerInstanceId = $source->providerInstanceId();
        if (null === $providerInstanceId || '' === $providerInstanceId) {
            $this->persistAndFlush($source);

            return $source;
        }

        $this->database()->transaction(function(DatabaseInterface $database) use ($source, $providerInstanceId): void {
            $this->lockProviderInstanceDependencies($database, $source->organizationId(), $providerInstanceId);
            $this->assertProviderInstanceLocked($database, $providerInstanceId, $source->organizationId());

            $this->persistAndFlush($source);
        });

        return $source;
    }

    public function findById(string $id): ?Source
    {
        $entity = $this->select()
            ->where([
                'uuid' => $id,
            ])
            ->fetchOne()
        ;

        return $entity instanceof Source ? $entity : null;
    }

    public function findByIdInOrganization(string $id, string $organizationId): ?Source
    {
        $entity = $this->select()
            ->where([
                'uuid'           => $id,
                'organizationId' => $organizationId,
            ])
            ->fetchOne()
        ;

        return $entity instanceof Source ? $entity : null;
    }

    public function findByUrl(string $url, ?string $organizationId = null): ?Source
    {
        $select = $this->select()->where([
            'canonicalUrl' => $url,
        ]);
        if (null !== $organizationId) {
            $select->where([
                'organizationId' => $organizationId,
            ]);
        }

        $entity = $select->fetchOne();

        return $entity instanceof Source ? $entity : null;
    }

    public function findByOrganization(string $organizationId): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'organizationId' => $organizationId,
                ])
                ->orderBy([
                    'createdAt' => 'DESC',
                ])
                ->fetchAll(),
            false,
        );
    }

    public function initiateDeletion(string $id, string $organizationId): SourceDeletionInitiation
    {
        $sourceDeletionInitiation = $this->database()->transaction(function(DatabaseInterface $database) use ($id, $organizationId): SourceDeletionInitiation {
            $rows = $database->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
                ->selectQuery($database->getPrefix())
                ->from('sources')
                ->columns('uuid', 'active', 'status')
                ->where('uuid', $id)
                ->where('organization_id', $organizationId)
                ->forUpdate()
                ->limit(1)
                ->fetchAll()
            ;
            $row = $rows[0] ?? null;

            if (! is_array($row)) {
                return new SourceDeletionInitiation(SourceDeletionInitiationOutcome::NotFound);
            }

            if (Source::STATUS_DELETING === (string) ($row['status'] ?? '')) {
                return new SourceDeletionInitiation(SourceDeletionInitiationOutcome::AlreadyDeleting);
            }

            $previousActive = (bool) ($row['active'] ?? false);
            $deletionRunId  = Uuid::uuid7()->toString();

            $database->update('sources')->values([
                'status'                    => Source::STATUS_DELETING,
                'active'                    => false,
                'deletion_run_id'           => $deletionRunId,
                'updated_at'                => new Fragment('CURRENT_TIMESTAMP'),
            ])->where('uuid', $id)
                ->where('organization_id', $organizationId)
                ->run()
            ;

            return new SourceDeletionInitiation(
                SourceDeletionInitiationOutcome::Initiated,
                $previousActive,
                $deletionRunId,
            );
        });

        if (SourceDeletionInitiationOutcome::Initiated === $sourceDeletionInitiation->outcome) {
            $this->detachFromHeap($id);
        }

        return $sourceDeletionInitiation;
    }

    public function revertInitiation(
        string $id,
        string $organizationId,
        bool $previousActive,
        string $deletionRunId,
    ): void {
        $this->database()->update('sources')->values([
            'status'                    => Source::STATUS_ACTIVE,
            'active'                    => $previousActive,
            'deletion_run_id'           => null,
            'updated_at'                => new Fragment('CURRENT_TIMESTAMP'),
        ])->where('uuid', $id)
            ->where('organization_id', $organizationId)
            ->where('status', Source::STATUS_DELETING)
            ->where('deletion_run_id', $deletionRunId)
            ->run()
        ;

        $this->detachFromHeap($id);
    }

    public function ensureDeletionRun(string $id, string $organizationId, string $deletionRunId): bool
    {
        $ensured = $this->database()->transaction(function(DatabaseInterface $database) use ($id, $organizationId, $deletionRunId): bool {
            $rows = $database->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
                ->selectQuery($database->getPrefix())
                ->from('sources')
                ->columns('status', 'deletion_run_id')
                ->where('uuid', $id)
                ->where('organization_id', $organizationId)
                ->forUpdate()
                ->limit(1)
                ->fetchAll()
            ;
            $row = $rows[0] ?? null;
            if (! is_array($row)) {
                return false;
            }

            if (Source::STATUS_DELETING === (string) ($row['status'] ?? '')) {
                return $deletionRunId === (string) ($row['deletion_run_id'] ?? '');
            }

            $database->update('sources')->values([
                'status'                    => Source::STATUS_DELETING,
                'active'                    => false,
                'deletion_run_id'           => $deletionRunId,
                'updated_at'                => new Fragment('CURRENT_TIMESTAMP'),
            ])->where('uuid', $id)
                ->where('organization_id', $organizationId)
                ->run()
            ;

            return true;
        });

        $this->detachFromHeap($id);

        return $ensured;
    }

    public function isDeletionRunCurrent(string $id, string $organizationId, string $deletionRunId): bool
    {
        $rows = $this->database()->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
            ->selectQuery($this->database()->getPrefix())
            ->from('sources')
            ->columns('uuid')
            ->where('uuid', $id)
            ->where('organization_id', $organizationId)
            ->where('status', Source::STATUS_DELETING)
            ->where('deletion_run_id', $deletionRunId)
            ->limit(1)
            ->fetchAll()
        ;

        return [] !== $rows;
    }

    public function deletionRunId(string $id, string $organizationId): ?string
    {
        $rows = $this->database()->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
            ->selectQuery($this->database()->getPrefix())
            ->from('sources')
            ->columns('deletion_run_id')
            ->where('uuid', $id)
            ->where('organization_id', $organizationId)
            ->where('status', Source::STATUS_DELETING)
            ->limit(1)
            ->fetchAll()
        ;
        $deletionRunId = $rows[0]['deletion_run_id'] ?? null;

        return is_string($deletionRunId) && '' !== $deletionRunId ? $deletionRunId : null;
    }

    public function recoverDeletionRun(string $id, string $organizationId, bool $force): ?string
    {
        $deletionRunId = $this->database()->transaction(function(DatabaseInterface $database) use ($id, $organizationId, $force): ?string {
            if ($force && ! $this->isLockGranted($database->getDriver(DatabaseInterface::WRITE)->query(
                'SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0))',
                [SourceDeletionExecutionLock::key($id, $organizationId)],
            )->fetchColumn())) {
                return null;
            }

            $rows = $database->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
                ->selectQuery($database->getPrefix())
                ->from('sources')
                ->columns('status', 'deletion_run_id')
                ->where('uuid', $id)
                ->where('organization_id', $organizationId)
                ->forUpdate()
                ->limit(1)
                ->fetchAll()
            ;
            $row = $rows[0] ?? null;
            if (! is_array($row) || Source::STATUS_DELETING !== (string) ($row['status'] ?? '')) {
                return null;
            }

            $currentDeletionRunId = $row['deletion_run_id'] ?? null;
            if (! $force && is_string($currentDeletionRunId) && '' !== $currentDeletionRunId) {
                return $currentDeletionRunId;
            }

            $newDeletionRunId = Uuid::uuid7()->toString();
            $database->update('sources')->values([
                'deletion_run_id' => $newDeletionRunId,
                'updated_at'      => new Fragment('CURRENT_TIMESTAMP'),
            ])->where('uuid', $id)
                ->where('organization_id', $organizationId)
                ->run()
            ;

            return $newDeletionRunId;
        });

        $this->detachFromHeap($id);

        return $deletionRunId;
    }

    public function countForOrganization(string $organizationId): int
    {
        return $this->select()
            ->where([
                'organizationId' => $organizationId,
            ])
            ->count()
        ;
    }

    public function countAll(): int
    {
        return $this->select()->count('*');
    }

    /**
     * @param array<string, mixed>        $scope
     * @param array<string, 'ASC'|'DESC'> $orderBy
     *
     * @return list<Source>
     */
    #[Override]
    public function findAll(array $scope = [], array $orderBy = []): array
    {
        /** @var non-empty-array<non-empty-string, non-empty-string> $sort */
        $sort = [] !== $orderBy ? $orderBy : [
            'createdAt' => 'DESC',
        ];

        return iterator_to_array(
            $this->select()->where($scope)->orderBy($sort)->fetchAll(),
            false,
        );
    }

    public function findBatchAfter(?string $afterId, int $limit): array
    {
        $select = $this->select()->orderBy('uuid', 'ASC')->limit($limit);
        if (null !== $afterId) {
            $select->where('uuid', '>', $afterId);
        }

        return iterator_to_array($select->fetchAll(), false);
    }

    public function findByProviderAuthorizationId(string $providerAuthorizationId, string $organizationId): array
    {
        return iterator_to_array(
            $this->select()
                ->where([
                    'organizationId'          => $organizationId,
                    'providerAuthorizationId' => $providerAuthorizationId,
                ])
                ->fetchAll(),
            false,
        );
    }

    public function findByProviderAuthorizationIds(array $providerAuthorizationIds, string $organizationId): array
    {
        if ([] === $providerAuthorizationIds) {
            return [];
        }

        return iterator_to_array(
            $this->select()
                ->where([
                    'organizationId'          => $organizationId,
                    'providerAuthorizationId' => [
                        'in' => $providerAuthorizationIds,
                    ],
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

    public function findLockedByProviderAuthorizationIds(array $providerAuthorizationIds, string $organizationId): array
    {
        if ([] === $providerAuthorizationIds) {
            return [];
        }

        return iterator_to_array(
            $this->select()
                ->where([
                    'organizationId'          => $organizationId,
                    'providerAuthorizationId' => [
                        'in' => $providerAuthorizationIds,
                    ],
                ])
                ->forUpdate()
                ->fetchAll(),
            false,
        );
    }

    private function assertProviderInstanceLocked(DatabaseInterface $database, string $providerInstanceId, string $organizationId): void
    {
        $rows = $database->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
            ->selectQuery($database->getPrefix())
            ->from('source_provider_instances')
            ->columns('uuid')
            ->where('uuid', $providerInstanceId)
            ->where('organization_id', $organizationId)
            ->forUpdate()
            ->limit(1)
            ->fetchAll()
        ;
        if ([] === $rows) {
            throw new SourceProviderInstanceUnavailableException('Source provider instance is not available.');
        }
    }

    private function lockProviderInstanceDependencies(
        DatabaseInterface $database,
        string $organizationId,
        string $providerInstanceId,
    ): void {
        $database->getDriver(DatabaseInterface::WRITE)->query(
            'SELECT pg_advisory_xact_lock(hashtext(?))',
            ['source-provider-instance-dependencies:' . $organizationId . ':' . $providerInstanceId],
        );
    }

    /**
     * The conditional initiation update bypasses the ORM change set, so a
     * cached heap entity would still carry the previous status. Detach it so
     * the next read hydrates fresh data.
     */
    private function detachFromHeap(string $id): void
    {
        $source = $this->findById($id);
        if ($source instanceof Source) {
            $this->orm->getHeap()->detach($source);
        }
    }

    private function isLockGranted(mixed $result): bool
    {
        return true === $result || 1 === $result || '1' === $result || 't' === $result;
    }
}
