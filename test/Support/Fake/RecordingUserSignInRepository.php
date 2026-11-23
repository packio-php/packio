<?php

declare(strict_types=1);

namespace PackioTest\Support\Fake;

use Cake\Chronos\Chronos;
use Packio\Identity\Domain\Entity\UserSignIn;
use Packio\Identity\Domain\Repository\UserSignInRepositoryInterface;

final class RecordingUserSignInRepository implements UserSignInRepositoryInterface
{
    /** @var list<UserSignIn> */
    public array $saved = [];

    public function save(UserSignIn $userSignIn): void
    {
        $this->saved[] = $userSignIn;
    }

    public function recentByUserUuid(string $userUuid, int $limit): array
    {
        return [];
    }

    public function lastSignInAtByUserUuids(array $userUuids): array
    {
        return [];
    }

    public function deleteOlderThan(Chronos $before): int
    {
        return 0;
    }
}
