<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Injection\Expression;
use Cycle\Database\Query\OnConflict;
use Cycle\ORM\Heap\Node;
use Cycle\ORM\SchemaInterface;
use Override;
use Packio\Package\Domain\Entity\Package;
use Packio\Package\Domain\Exception\PackageSourceConflictException;
use Packio\Package\Domain\Repository\PackageRepositoryInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;
use Throwable;

use function array_key_exists;
use function array_values;
use function is_array;
use function is_string;
use function iterator_to_array;
use function trim;

/**
 * @extends AbstractWriteRepository<Package>
 */
final class PackageRepository extends AbstractWriteRepository implements PackageRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = Package::class;

    public function countAll(): int
    {
        return $this->select()->count('*');
    }

    public function countForOrganization(string $organizationId): int
    {
        return $this->select()->where('organizationId', $organizationId)->count('*');
    }

    public function save(Package $package): Package
    {
        $this->persistAndFlush($package);

        return $package;
    }

    public function upsertByNaturalKey(Package $package): Package
    {
        $package->setIdentifier(Uuid::uuid7());

        /** @var null|Package $managedPackage */
        $managedPackage = null;

        try {
            return $this->database()->transaction(
                function(DatabaseInterface $database) use ($package, &$managedPackage): Package {
                    $this->insertCandidate($database, $package);

                    $managedPackage = $this->findLockedByNaturalKey(
                        $database,
                        $package->organizationId(),
                        $package->name(),
                    );
                    if (! $managedPackage instanceof Package) {
                        throw new RuntimeException('Package was not found after its natural-key insert.');
                    }

                    $existingSourceId  = trim($managedPackage->sourceId());
                    $candidateSourceId = trim($package->sourceId());
                    if (
                        '' !== $existingSourceId
                        && '' !== $candidateSourceId
                        && $existingSourceId !== $candidateSourceId
                    ) {
                        throw new PackageSourceConflictException($package->name());
                    }

                    $managedPackage->updateMetadata(
                        $package->type(),
                        $package->description(),
                        $managedPackage->visibility(),
                        $candidateSourceId,
                        $package->sourceType(),
                        $package->sourceUrl(),
                    );
                    if (null !== $package->readmeHtml()) {
                        $managedPackage->updateReadmeHtml($package->readmeHtml());
                    }
                    $this->persistAndFlush($managedPackage);

                    return $managedPackage;
                },
            );
        } catch (Throwable $exception) {
            if ($managedPackage instanceof Package) {
                $this->orm->getHeap()->detach($managedPackage);
            }

            throw $exception;
        }
    }

    public function incrementDownloadCount(string $organizationId, string $name): bool
    {
        return $this->database()
            ->update('packages', [
                'download_count' => new Expression('download_count + 1'),
                'updated_at'     => Chronos::now('UTC')->toDateTimeString(),
            ])
            ->where('organization_id', $organizationId)
            ->where('name', $name)
            ->run() > 0
        ;
    }

    public function findByName(string $name, string $organizationId): ?Package
    {
        $select = $this->select()->where([
            'name'           => $name,
            'organizationId' => $organizationId,
        ]);

        $entity = $select->fetchOne();

        return $entity instanceof Package ? $entity : null;
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

    /**
     * @param array<string, mixed>        $scope
     * @param array<string, 'ASC'|'DESC'> $orderBy
     *
     * @return list<Package>
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

    private function insertCandidate(DatabaseInterface $database, Package $package): void
    {
        $now = Chronos::now('UTC')->toDateTimeString();

        $database
            ->insert('packages')
            ->values([
                'uuid'            => $package->getIdentifier()->toString(),
                'organization_id' => $package->organizationId(),
                'name'            => $package->name(),
                'type'            => $package->type(),
                'description'     => $package->description(),
                'visibility'      => $package->visibility(),
                'active'          => $package->isActive(),
                'source_id'       => $package->sourceId(),
                'source_type'     => $package->sourceType(),
                'source_url'      => $package->sourceUrl(),
                'readme_html'     => $package->readmeHtml(),
                'download_count'  => 0,
                'created_at'      => $now,
                'updated_at'      => $now,
            ])
            ->onConflict(OnConflict::target(['organization_id', 'name'])->doNothing())
            ->run()
        ;
    }

    private function findLockedByNaturalKey(
        DatabaseInterface $database,
        string $organizationId,
        string $name,
    ): ?Package {
        $role    = $this->orm->resolveRole(Package::class);
        $schema  = $this->orm->getSchema();
        $table   = $schema->define($role, SchemaInterface::TABLE);
        $columns = $schema->define($role, SchemaInterface::COLUMNS);
        if (! is_string($table) || '' === $table || ! is_array($columns)) {
            throw new RuntimeException('Package persistence schema is invalid.');
        }

        foreach ($columns as $field => $column) {
            if (! is_string($field) || ! is_string($column)) {
                throw new RuntimeException('Package persistence column mapping is invalid.');
            }
        }

        /** @var array<string, string> $columns */
        $rows = $database
            ->getDriver(DatabaseInterface::WRITE)
            ->getQueryBuilder()
            ->selectQuery($database->getPrefix())
            ->from($table)
            ->columns(array_values($columns))
            ->where('organization_id', $organizationId)
            ->where('name', $name)
            ->forUpdate()
            ->limit(1)
            ->fetchAll()
        ;
        $row = $rows[0] ?? null;
        if (! is_array($row)) {
            return null;
        }

        $entityData = [];
        foreach ($columns as $field => $column) {
            if (! array_key_exists($column, $row)) {
                throw new RuntimeException('Locked package row does not match the persistence schema.');
            }

            $entityData[$field] = $row[$column];
        }

        $loaded = $this->orm->getHeap()->find($role, [
            'uuid' => $entityData['uuid'] ?? null,
        ]);
        if (null !== $loaded) {
            $this->orm->getHeap()->detach($loaded);
        }

        $package = $this->orm->make(Package::class, $entityData, Node::MANAGED, true);

        return $package instanceof Package ? $package : null;
    }
}
