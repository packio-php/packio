<?php

declare(strict_types=1);

namespace Packio\Release\Infrastructure\Persistence\Cycle;

use const JSON_THROW_ON_ERROR;

use Cake\Chronos\Chronos;
use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Injection\Expression;
use Cycle\Database\Query\OnConflict;
use Cycle\ORM\Heap\Node;
use Cycle\ORM\SchemaInterface;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Domain\Repository\ReleaseRepositoryInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;
use Throwable;

use function array_key_exists;
use function array_values;
use function is_array;
use function is_string;
use function iterator_to_array;
use function json_encode;

/**
 * @extends AbstractWriteRepository<Release>
 */
final class ReleaseRepository extends AbstractWriteRepository implements ReleaseRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = Release::class;

    public function save(Release $release): Release
    {
        $this->persistAndFlush($release);

        return $release;
    }

    public function upsertByNaturalKey(Release $release): Release
    {
        $release->setIdentifier(Uuid::uuid7());

        /** @var null|Release $managedRelease */
        $managedRelease = null;

        try {
            return $this->database()->transaction(
                function(DatabaseInterface $database) use ($release, &$managedRelease): Release {
                    $this->insertCandidate($database, $release);

                    $managedRelease = $this->findLockedByNaturalKey(
                        $database,
                        $release->organizationId(),
                        $release->packageName(),
                        $release->version(),
                    );
                    if (! $managedRelease instanceof Release) {
                        throw new RuntimeException('Release was not found after its natural-key insert.');
                    }

                    $managedRelease->updatePayload(
                        $release->reference(),
                        $release->composerData(),
                        $release->sourceData(),
                        $release->distData(),
                        $release->releasedAt(),
                        $release->upstreamReference(),
                        $release->referenceType(),
                    );
                    $this->persistAndFlush($managedRelease);

                    return $managedRelease;
                },
            );
        } catch (Throwable $exception) {
            if ($managedRelease instanceof Release) {
                $this->orm->getHeap()->detach($managedRelease);
            }

            throw $exception;
        }
    }

    public function updateDistStorageMetadataByNaturalKey(
        string $organizationId,
        string $packageName,
        string $version,
        string $expectedReference,
        array $storageData,
    ): ?Release {
        /** @var null|Release $managedRelease */
        $managedRelease = null;

        try {
            return $this->database()->transaction(
                function(DatabaseInterface $database) use (
                    $organizationId,
                    $packageName,
                    $version,
                    $expectedReference,
                    $storageData,
                    &$managedRelease,
                ): ?Release {
                    $managedRelease = $this->findLockedByNaturalKey(
                        $database,
                        $organizationId,
                        $packageName,
                        $version,
                    );
                    if (! $managedRelease instanceof Release || $managedRelease->reference() !== $expectedReference) {
                        return null;
                    }

                    $managedRelease->updateDistStorageMetadata($storageData);
                    $this->persistAndFlush($managedRelease);

                    return $managedRelease;
                },
            );
        } catch (Throwable $exception) {
            if ($managedRelease instanceof Release) {
                $this->orm->getHeap()->detach($managedRelease);
            }

            throw $exception;
        }
    }

    public function incrementDownloadCount(string $organizationId, string $packageName, string $version): bool
    {
        return $this->database()
            ->update('releases', [
                'download_count' => new Expression('download_count + 1'),
                'updated_at'     => Chronos::now('UTC')->toDateTimeString(),
            ])
            ->where('organization_id', $organizationId)
            ->where('package_name', $packageName)
            ->where('version', $version)
            ->run() > 0
        ;
    }

    public function findByPackageAndVersion(string $packageName, string $version, string $organizationId): ?Release
    {
        $select = $this->select()->where([
            'packageName'    => $packageName,
            'version'        => $version,
            'organizationId' => $organizationId,
        ]);

        $entity = $select->fetchOne();

        return $entity instanceof Release ? $entity : null;
    }

    public function findByPackageName(string $packageName, string $organizationId): array
    {
        $select = $this->select()->where([
            'packageName'    => $packageName,
            'organizationId' => $organizationId,
        ]);

        return iterator_to_array(
            $select
                ->orderBy([
                    'createdAt' => 'DESC',
                ])
                ->fetchAll(),
            false,
        );
    }

    public function findActiveVersionsByOrganization(string $organizationId): array
    {
        $rows = $this->database()
            ->select(['package_name', 'version'])
            ->from('releases')
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->orderBy('created_at', 'DESC')
            ->fetchAll()
        ;

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'package_name' => (string) $row['package_name'],
                'version'      => (string) $row['version'],
            ];
        }

        return $result;
    }

    public function findPackageKeys(): array
    {
        $rows = $this->database()
            ->select(['organization_id', 'package_name'])
            ->distinct()
            ->from('releases')
            ->fetchAll()
        ;

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'organization_id' => (string) $row['organization_id'],
                'package_name'    => (string) $row['package_name'],
            ];
        }

        return $result;
    }

    private function insertCandidate(DatabaseInterface $database, Release $release): void
    {
        $now = Chronos::now('UTC')->toDateTimeString();

        $database
            ->insert('releases')
            ->values([
                'uuid'               => $release->getIdentifier()->toString(),
                'organization_id'    => $release->organizationId(),
                'package_name'       => $release->packageName(),
                'version'            => $release->version(),
                'reference'          => $release->reference(),
                'upstream_reference' => $release->upstreamReference(),
                'reference_type'     => $release->referenceType(),
                'active'             => true,
                'composer_data'      => json_encode($release->composerData(), JSON_THROW_ON_ERROR),
                'source_data'        => json_encode($release->sourceData(), JSON_THROW_ON_ERROR),
                'dist_data'          => json_encode($release->distData(), JSON_THROW_ON_ERROR),
                'download_count'     => 0,
                'released_at'        => $release->releasedAt()?->toDateTimeString(),
                'created_at'         => $now,
                'updated_at'         => $now,
            ])
            ->onConflict(OnConflict::target(['organization_id', 'package_name', 'version'])->doNothing())
            ->run()
        ;
    }

    private function findLockedByNaturalKey(
        DatabaseInterface $database,
        string $organizationId,
        string $packageName,
        string $version,
    ): ?Release {
        $role    = $this->orm->resolveRole(Release::class);
        $schema  = $this->orm->getSchema();
        $table   = $schema->define($role, SchemaInterface::TABLE);
        $columns = $schema->define($role, SchemaInterface::COLUMNS);
        if (! is_string($table) || '' === $table || ! is_array($columns)) {
            throw new RuntimeException('Release persistence schema is invalid.');
        }

        foreach ($columns as $field => $column) {
            if (! is_string($field) || ! is_string($column)) {
                throw new RuntimeException('Release persistence column mapping is invalid.');
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
            ->where('package_name', $packageName)
            ->where('version', $version)
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
                throw new RuntimeException('Locked release row does not match the persistence schema.');
            }

            $entityData[$field] = $row[$column];
        }

        $loaded = $this->orm->getHeap()->find($role, [
            'uuid' => $entityData['uuid'] ?? null,
        ]);
        if (null !== $loaded) {
            $this->orm->getHeap()->detach($loaded);
        }

        $release = $this->orm->make(Release::class, $entityData, Node::MANAGED, true);

        return $release instanceof Release ? $release : null;
    }
}
