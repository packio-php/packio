<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle;

use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\ORM\EntityManager;
use Cycle\ORM\Transaction\Runner;
use Packio\Identity\Domain\Dto\RegistryAccessTokenRegenerationOutcome;
use Packio\Identity\Domain\Dto\RegistryAccessTokenRegenerationSpec;
use Packio\Identity\Domain\Entity\RegistryAccessToken;
use Packio\Identity\Domain\Repository\RegistryAccessTokenRepositoryInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;
use Throwable;

use function iterator_to_array;

/**
 * @extends AbstractWriteRepository<RegistryAccessToken>
 */
final class RegistryAccessTokenRepository extends AbstractWriteRepository implements RegistryAccessTokenRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS = RegistryAccessToken::class;

    public function regenerate(
        string $organizationId,
        string $userId,
        string $tokenId,
        RegistryAccessTokenRegenerationSpec $registryAccessTokenRegenerationSpec,
    ): ?RegistryAccessTokenRegenerationOutcome {
        try {
            return $this->database()->transaction(function() use ($organizationId, $userId, $tokenId, $registryAccessTokenRegenerationSpec): ?RegistryAccessTokenRegenerationOutcome {
                // Refresh any cached entity before the scoped, locking lookup.
                $role   = $this->orm->resolveRole(RegistryAccessToken::class);
                $loaded = $this->orm->getHeap()->find($role, [
                    'uuid' => $tokenId,
                ]);
                if (null !== $loaded) {
                    $this->orm->getHeap()->detach($loaded);
                }

                $previous = $this->forUpdate()->findByIdForOwnerInOrganization($tokenId, $userId, $organizationId);
                if (! $previous instanceof RegistryAccessToken) {
                    return null;
                }

                $registryAccessToken = $registryAccessTokenRegenerationSpec->issueFor($previous);
                $manager             = new EntityManager($this->orm);
                $manager->persist($registryAccessToken);
                $manager->run(true, Runner::outerTransaction());

                $manager = new EntityManager($this->orm);
                $manager->delete($previous);
                $manager->run(true, Runner::outerTransaction());

                return new RegistryAccessTokenRegenerationOutcome($previous, $registryAccessToken);
            });
        } catch (Throwable $exception) {
            $this->orm->getHeap()->clean();

            throw new RepositoryPersistException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function save(RegistryAccessToken $registryAccessToken): RegistryAccessToken
    {
        $this->persistAndFlush($registryAccessToken);

        return $registryAccessToken;
    }

    public function findById(string $id): ?RegistryAccessToken
    {
        $entity = $this->select()
            ->where([
                'uuid' => $id,
            ])
            ->fetchOne()
        ;

        return $entity instanceof RegistryAccessToken ? $entity : null;
    }

    public function findByIdForOwnerInOrganization(string $id, string $ownerUserId, string $organizationId): ?RegistryAccessToken
    {
        $entity = $this->select()
            ->where([
                'uuid'           => $id,
                'ownerUserId'    => $ownerUserId,
                'organizationId' => $organizationId,
            ])
            ->fetchOne()
        ;

        return $entity instanceof RegistryAccessToken ? $entity : null;
    }

    public function findByTokenHash(string $tokenHash): ?RegistryAccessToken
    {
        $entity = $this->select()
            ->where([
                'tokenHash' => $tokenHash,
            ])
            ->fetchOne()
        ;

        return $entity instanceof RegistryAccessToken ? $entity : null;
    }

    public function latestForOrganization(string $organizationId): array
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
}
