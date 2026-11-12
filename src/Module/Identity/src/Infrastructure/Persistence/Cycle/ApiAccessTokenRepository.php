<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManager;
use Cycle\ORM\Heap\Node;
use Cycle\ORM\SchemaInterface;
use Cycle\ORM\Transaction\Runner;
use Packio\Identity\Domain\Dto\ApiAccessTokenRegenerationOutcome;
use Packio\Identity\Domain\Dto\ApiAccessTokenRegenerationSpec;
use Packio\Identity\Domain\Entity\ApiAccessToken;
use Packio\Identity\Domain\Repository\ApiAccessTokenRepositoryInterface;
use RuntimeException;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;
use Throwable;

use function array_key_exists;
use function array_values;
use function is_array;
use function is_string;
use function iterator_to_array;

/**
 * @extends AbstractWriteRepository<ApiAccessToken>
 */
final class ApiAccessTokenRepository extends AbstractWriteRepository implements ApiAccessTokenRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = ApiAccessToken::class;

    public function save(ApiAccessToken $apiAccessToken): ApiAccessToken
    {
        $this->persistAndFlush($apiAccessToken);

        return $apiAccessToken;
    }

    public function regenerate(
        string $userId,
        string $tokenId,
        ApiAccessTokenRegenerationSpec $apiAccessTokenRegenerationSpec,
    ): ?ApiAccessTokenRegenerationOutcome {
        try {
            return $this->database()->transaction(
                function(DatabaseInterface $database) use ($userId, $tokenId, $apiAccessTokenRegenerationSpec): ?ApiAccessTokenRegenerationOutcome {
                    $database->query('SELECT pg_advisory_xact_lock(hashtext(?))', ['identity-api-access-token:' . $userId]);

                    $revoked = $this->findLockedByIdForUser($database, $tokenId, $userId);
                    if (! $revoked instanceof ApiAccessToken || $revoked->isRevoked() || $revoked->isExpired(new Chronos())) {
                        return null;
                    }

                    $apiAccessToken = $apiAccessTokenRegenerationSpec->issueFor($revoked);

                    $this->runInOuterTransaction($apiAccessToken);
                    $revoked->revoke(new Chronos());
                    $this->runInOuterTransaction($revoked);

                    return new ApiAccessTokenRegenerationOutcome($revoked, $apiAccessToken);
                },
            );
        } catch (Throwable $exception) {
            $this->orm->getHeap()->clean();

            throw new RepositoryPersistException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function findByTokenHash(string $tokenHash): ?ApiAccessToken
    {
        $entity = $this->select()
            ->where([
                'tokenHash' => $tokenHash,
            ])
            ->fetchOne()
        ;

        return $entity instanceof ApiAccessToken ? $entity : null;
    }

    public function findByIdForUser(string $id, string $userId): ?ApiAccessToken
    {
        $entity = $this->select()
            ->where([
                'uuid'   => $id,
                'userId' => $userId,
            ])
            ->fetchOne()
        ;

        return $entity instanceof ApiAccessToken ? $entity : null;
    }

    public function latestForUser(string $userId): array
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

    private function findLockedByIdForUser(DatabaseInterface $database, string $tokenId, string $userId): ?ApiAccessToken
    {
        $role    = $this->orm->resolveRole(ApiAccessToken::class);
        $schema  = $this->orm->getSchema();
        $table   = $schema->define($role, SchemaInterface::TABLE);
        $columns = $schema->define($role, SchemaInterface::COLUMNS);
        if (! is_string($table) || '' === $table || ! is_array($columns)) {
            throw new RuntimeException('API access token persistence schema is invalid.');
        }

        foreach ($columns as $field => $column) {
            if (! is_string($field) || ! is_string($column)) {
                throw new RuntimeException('API access token persistence column mapping is invalid.');
            }
        }

        /** @var array<string, string> $columns */
        $rows = $database
            ->getDriver(DatabaseInterface::WRITE)
            ->getQueryBuilder()
            ->selectQuery($database->getPrefix())
            ->from($table)
            ->columns(array_values($columns))
            ->where('uuid', $tokenId)
            ->where('user_id', $userId)
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
                throw new RuntimeException('Locked API access token row does not match the persistence schema.');
            }

            $entityData[$field] = $row[$column];
        }

        $loaded = $this->orm->getHeap()->find($role, [
            'uuid' => $entityData['uuid'] ?? null,
        ]);
        if (null !== $loaded) {
            $this->orm->getHeap()->detach($loaded);
        }

        $apiAccessToken = $this->orm->make(ApiAccessToken::class, $entityData, Node::MANAGED, true);

        return $apiAccessToken instanceof ApiAccessToken ? $apiAccessToken : null;
    }

    private function runInOuterTransaction(ApiAccessToken $apiAccessToken): void
    {
        // A dedicated EntityManager keeps this operation's UnitOfWork isolated:
        // only the two tokens of the regeneration end up in the outer transaction.
        // The inherited private EntityManager of AbstractWriteRepository is not
        // accessible here and must not be reused for the same reason.
        $entityManager = new EntityManager($this->orm);
        $entityManager->persist($apiAccessToken);
        $entityManager->run(true, Runner::outerTransaction());
    }
}
