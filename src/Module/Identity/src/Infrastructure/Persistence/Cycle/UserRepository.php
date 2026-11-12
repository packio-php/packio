<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Persistence\Cycle;

use Common\Persistence\Cycle\HasDatabaseAccessTrait;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Injection\Fragment;
use Cycle\ORM\Select;
use InvalidArgumentException;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\Value\UserListFilterCriteria;
use Packio\Identity\Domain\Value\UserListStatus;
use Packio\Identity\Domain\Value\UserModerationOutcome;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Sirix\Cycle\Extension\Repository\AbstractWriteRepository;

use function array_unique;
use function array_values;
use function ceil;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function iterator_to_array;
use function json_decode;
use function max;
use function min;
use function str_replace;
use function strtolower;
use function trim;

/**
 * @extends AbstractWriteRepository<User>
 */
final class UserRepository extends AbstractWriteRepository implements UserRepositoryInterface
{
    use HasDatabaseAccessTrait;

    private const string ENTITY_CLASS    = User::class;
    private const int MAX_ID_LOOKUP_SIZE = 100;

    public function save(User $user): User
    {
        $this->persistAndFlush($user);

        return $user;
    }

    public function findById(string $id): ?User
    {
        $entity = $this->select()
            ->where([
                'uuid' => $id,
            ])
            ->fetchOne()
        ;

        return $entity instanceof User ? $entity : null;
    }

    public function findByEmail(string $email): ?User
    {
        $entity = $this->select()
            ->where([
                'email' => strtolower(trim($email)),
            ])
            ->fetchOne()
        ;

        return $entity instanceof User ? $entity : null;
    }

    public function banUser(string $userId, string $actorId, string $reason): UserModerationOutcome
    {
        $outcome = $this->database()->transaction(function(DatabaseInterface $database) use ($userId, $actorId, $reason): UserModerationOutcome {
            $database->query('SELECT pg_advisory_xact_lock(hashtext(?))', ['identity-user-moderation']);

            $users = $database->getDriver(DatabaseInterface::WRITE)->getQueryBuilder()
                ->selectQuery($database->getPrefix())
                ->from('users')
                ->columns('uuid', 'roles', 'banned_at')
                ->where('uuid', $userId)
                ->forUpdate()
                ->limit(1)
                ->fetchAll()
            ;
            $user = $users[0] ?? null;

            if (! is_array($user)) {
                return UserModerationOutcome::UserNotFound;
            }

            if ($userId === $actorId) {
                return UserModerationOutcome::SelfBanForbidden;
            }

            if (null !== $user['banned_at']) {
                return UserModerationOutcome::AlreadyBanned;
            }

            if ($this->isAdmin($user['roles']) && $this->activeAdminCountOnWrite($database) < 2) {
                return UserModerationOutcome::LastActiveAdmin;
            }

            $database->update('users')->values([
                'banned_at'         => new Fragment('CURRENT_TIMESTAMP'),
                'ban_reason'        => $reason,
                'banned_by_user_id' => $actorId,
                'updated_at'        => new Fragment('CURRENT_TIMESTAMP'),
            ])->where('uuid', $userId)->run();

            return UserModerationOutcome::Success;
        });

        if (UserModerationOutcome::Success === $outcome) {
            $this->invalidateUser($userId);
        }

        return $outcome;
    }

    public function unbanUser(string $userId): UserModerationOutcome
    {
        $outcome = $this->database()->transaction(function(DatabaseInterface $database) use ($userId): UserModerationOutcome {
            $database->query('SELECT pg_advisory_xact_lock(hashtext(?))', ['identity-user-moderation']);

            $changed = $database->update('users')->values([
                'banned_at'         => null,
                'ban_reason'        => null,
                'banned_by_user_id' => null,
                'updated_at'        => new Fragment('CURRENT_TIMESTAMP'),
            ])->where('uuid', $userId)->where('banned_at', '!=', null)->run();

            if (1 === $changed) {
                return UserModerationOutcome::Success;
            }

            return $this->findById($userId) instanceof User
                ? UserModerationOutcome::NotBanned
                : UserModerationOutcome::UserNotFound;
        });

        if (UserModerationOutcome::Success === $outcome) {
            $this->invalidateUser($userId);
        }

        return $outcome;
    }

    public function findPaginated(PaginationCriteria $paginationCriteria, UserListFilterCriteria $userListFilterCriteria): array
    {
        $select = $this->filteredSelect($paginationCriteria, $userListFilterCriteria)
            ->orderBy([
                'email' => 'ASC',
                'uuid'  => 'ASC',
            ])
        ;

        $total              = $select->count('*');
        $currentPage        = min($paginationCriteria->page, max(1, (int) ceil($total / $paginationCriteria->perPage)));
        $paginationCriteria = $paginationCriteria->withPage($currentPage);

        /** @var list<User> $items */
        $items = iterator_to_array(
            $select
                ->offset($paginationCriteria->offset())
                ->limit($paginationCriteria->limit())
                ->fetchAll(),
            false,
        );

        return [
            'items'       => $items,
            'total'       => $total,
            'currentPage' => $currentPage,
            'perPage'     => $paginationCriteria->perPage,
        ];
    }

    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if ([] === $ids) {
            return [];
        }

        if (count($ids) > self::MAX_ID_LOOKUP_SIZE) {
            throw new InvalidArgumentException('At most 100 distinct user IDs may be requested.');
        }

        return $this->findAllByIds($ids);
    }

    public function findAllByIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if ([] === $ids) {
            return [];
        }

        return iterator_to_array(
            $this->select()
                ->where('uuid', 'IN', $ids)
                ->orderBy([
                    'email' => 'ASC',
                    'uuid'  => 'ASC',
                ])
                ->fetchAll(),
            false,
        );
    }

    public function countAll(): int
    {
        return $this->scalarCount('SELECT COUNT(*) AS count FROM users');
    }

    public function countAdmins(): int
    {
        return $this->scalarCount('SELECT COUNT(*) AS count FROM users WHERE roles::jsonb @> ?::jsonb', ['["admin"]']);
    }

    public function activeAdminsCount(): int
    {
        return $this->activeAdminCountOnWrite($this->database());
    }

    private function invalidateUser(string $userId): void
    {
        $user = $this->findById($userId);

        if ($user instanceof User) {
            $this->orm->getHeap()->detach($user);
        }
    }

    private function activeAdminCountOnWrite(DatabaseInterface $database): int
    {
        return $this->scalarCount(
            'SELECT COUNT(*) AS count FROM users WHERE banned_at IS NULL AND roles::jsonb @> ?::jsonb',
            ['["admin"]'],
            $database,
        );
    }

    /** @return Select<User> */
    private function filteredSelect(PaginationCriteria $paginationCriteria, UserListFilterCriteria $userListFilterCriteria): Select
    {
        $select = $this->select();

        if (UserListStatus::Active === $userListFilterCriteria->status) {
            $select->where([
                'bannedAt' => null,
            ]);
        }

        if (UserListStatus::Banned === $userListFilterCriteria->status) {
            $select->where('bannedAt', '!=', null);
        }

        if (null !== $paginationCriteria->search && '' !== $paginationCriteria->search) {
            $search = '%' . $this->escapeLikePattern($paginationCriteria->search) . '%';
            $select->where(new Fragment('(email ILIKE ? OR display_name ILIKE ?)', $search, $search));
        }

        return $select;
    }

    /**
     * @param non-empty-string $query
     * @param list<mixed>      $parameters
     */
    private function scalarCount(string $query, array $parameters = [], ?DatabaseInterface $database = null): int
    {
        $database ??= $this->database();
        $rows = $database->getDriver(DatabaseInterface::WRITE)->query($query, $parameters)->fetchAll();
        $row  = $rows[0] ?? [];

        return is_array($row) && is_int($row['count'] ?? null) ? $row['count'] : (int) ($row['count'] ?? 0);
    }

    private function escapeLikePattern(string $search): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
    }

    private function isAdmin(mixed $roles): bool
    {
        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }

        return is_array($roles) && in_array('admin', $roles, true);
    }
}
