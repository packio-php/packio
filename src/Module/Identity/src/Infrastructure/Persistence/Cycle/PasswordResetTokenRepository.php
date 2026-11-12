<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use InvalidArgumentException;
use Override;
use Packio\Identity\Domain\Entity\PasswordResetToken;
use Packio\Identity\Domain\Repository\PasswordResetTokenRepositoryInterface;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\Cycle\Extension\Exception\RepositoryPersistException;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

/**
 * @extends AbstractWriteRepository<PasswordResetToken>
 */
final class PasswordResetTokenRepository extends AbstractWriteRepository implements PasswordResetTokenRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string TABLE_NAME   = 'password_reset_tokens';
    private const string ENTITY_CLASS = PasswordResetToken::class;

    /**
     * @throws RepositoryPersistException
     */
    public function save(PasswordResetToken $passwordResetToken): void
    {
        $this->persistAndFlush($passwordResetToken);
    }

    #[Override]
    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void
    {
        if (! $entity instanceof PasswordResetToken) {
            throw new InvalidArgumentException('Expected a password reset token.');
        }
        // Match the explicit deletion used for verification codes; avoid ORM delete behavior wrapping.
        $this->database()->delete(self::TABLE_NAME, [
            'uuid' => $entity->getIdentifier()->toString(),
        ])->run();
        $this->orm->getHeap()->detach($entity);
    }

    public function findBySelector(string $selector): ?PasswordResetToken
    {
        return $this->findOne([
            'selector' => $selector,
        ]);
    }

    public function findActiveBySelector(string $selector): ?PasswordResetToken
    {
        return $this->findOne([
            'selector' => $selector,
            'usedAt'   => null,
        ]);
    }

    public function deleteActiveByUserUuid(string $userUuid, ?string $excludeTokenUuid = null): int
    {
        $select = $this->select();

        $select->where('userUuid', $userUuid);
        $select->where('usedAt', null);

        /** @var iterable<PasswordResetToken> $activeTokens */
        $activeTokens = $select->fetchAll();

        $database = $this->database();

        $count = 0;
        foreach ($activeTokens as $activeToken) {
            if (null !== $excludeTokenUuid && $activeToken->getIdentifier()->toString() === $excludeTokenUuid) {
                continue;
            }

            $count += $database->delete(self::TABLE_NAME, [
                'uuid' => $activeToken->getIdentifier()->toString(),
            ])->run();
        }

        return $count;
    }

    public function deleteExpired(): int
    {
        $now = new Chronos();

        return $this->database()
            ->delete(self::TABLE_NAME)
            ->where('used_at', '!=', null)
            ->orWhere('expires_at', '<', $now)
            ->run()
        ;
    }
}
