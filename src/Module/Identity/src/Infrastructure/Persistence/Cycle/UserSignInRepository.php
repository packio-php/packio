<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\Injection\Fragment;
use Packio\Identity\Domain\Entity\UserSignIn;
use Packio\Identity\Domain\Repository\UserSignInRepositoryInterface;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;
use Sirix\Cycle\Extension\Typecast\Chronos\ChronosNativeTypecast;

use function is_string;
use function iterator_to_array;

/**
 * @extends AbstractWriteRepository<UserSignIn>
 */
final class UserSignInRepository extends AbstractWriteRepository implements UserSignInRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string TABLE_NAME   = 'user_sign_ins';
    private const string ENTITY_CLASS = UserSignIn::class;

    public function save(UserSignIn $userSignIn): void
    {
        $this->persistAndFlush($userSignIn);
    }

    public function recentByUserUuid(string $userUuid, int $limit): array
    {
        return iterator_to_array(
            $this->select()
                ->where('userUuid', $userUuid)
                ->orderBy([
                    'createdAt' => 'DESC',
                    'uuid'      => 'DESC',
                ])
                ->limit($limit)
                ->fetchAll(),
            false,
        );
    }

    public function lastSignInAtByUserUuids(array $userUuids): array
    {
        if ([] === $userUuids) {
            return [];
        }

        $rows = $this->database()
            ->select()
            ->columns(['user_uuid', new Fragment('MAX(created_at) AS last_sign_in_at')])
            ->from(self::TABLE_NAME)
            ->where('user_uuid', 'in', $userUuids)
            ->groupBy('user_uuid')
            ->fetchAll()
        ;

        $result = [];
        foreach ($rows as $row) {
            $lastSignInAt = ChronosNativeTypecast::toChronos($row['last_sign_in_at'] ?? null);
            if (is_string($row['user_uuid'] ?? null) && $lastSignInAt instanceof Chronos) {
                $result[$row['user_uuid']] = $lastSignInAt;
            }
        }

        return $result;
    }

    public function deleteOlderThan(Chronos $before): int
    {
        return $this->database()
            ->delete(self::TABLE_NAME, [
                'created_at' => [
                    '<' => $before,
                ],
            ])
            ->run()
        ;
    }
}
