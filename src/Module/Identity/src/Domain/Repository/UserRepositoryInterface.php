<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Repository;

use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Value\UserListFilterCriteria;
use Packio\Identity\Domain\Value\UserModerationOutcome;
use Packio\Shared\Domain\Pagination\PaginationCriteria;

interface UserRepositoryInterface
{
    public function save(User $user): User;

    public function findById(string $id): ?User;

    /** @phpstan-impure */
    public function findByEmail(string $email): ?User;

    public function banUser(string $userId, string $actorId, string $reason): UserModerationOutcome;

    public function unbanUser(string $userId): UserModerationOutcome;

    /**
     * @return array{items: list<User>, total: int, currentPage: int, perPage: int}
     */
    public function findPaginated(PaginationCriteria $paginationCriteria, UserListFilterCriteria $userListFilterCriteria): array;

    /**
     * @param list<string> $ids
     *
     * @return list<User>
     */
    public function findByIds(array $ids): array;

    /**
     * Batch lookup for server-derived IDs, without the admin selector limit.
     *
     * @param list<string> $ids
     *
     * @return list<User>
     */
    public function findAllByIds(array $ids): array;

    public function countAll(): int;

    /** Includes banned users with the admin role. */
    public function countAdmins(): int;

    /** Counts only non-banned users with the admin role. */
    public function activeAdminsCount(): int;
}
