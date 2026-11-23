<?php

declare(strict_types=1);

namespace Packio\Identity\Domain\Repository;

use Cake\Chronos\Chronos;
use Packio\Identity\Domain\Entity\UserSignIn;

interface UserSignInRepositoryInterface
{
    public function save(UserSignIn $userSignIn): void;

    /**
     * @return list<UserSignIn>
     */
    public function recentByUserUuid(string $userUuid, int $limit): array;

    /**
     * @param list<string> $userUuids
     *
     * @return array<string, Chronos> latest sign-in time keyed by user UUID; users without sign-ins are absent
     */
    public function lastSignInAtByUserUuids(array $userUuids): array;

    public function deleteOlderThan(Chronos $before): int;
}
