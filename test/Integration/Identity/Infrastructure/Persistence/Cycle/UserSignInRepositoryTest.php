<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\Persistence\Cycle;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Identity\Domain\Entity\UserSignIn;
use Packio\Identity\Domain\Repository\UserSignInRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function array_keys;

final class UserSignInRepositoryTest extends IntegrationTestCase
{
    #[Test]
    public function itReturnsRecentSignInsNewestFirstWithinLimit(): void
    {
        $userA = Uuid::uuid7()->toString();
        $userB = Uuid::uuid7()->toString();

        $this->saveSignIn($userA, 'gitlab', 3);
        $this->saveSignIn($userA, 'github', 2);
        $this->saveSignIn($userA, 'password', 1, '203.0.113.7', 'Mozilla/5.0');
        $this->saveSignIn($userB, 'password', 1);

        $recent = $this->repository()->recentByUserUuid($userA, 2);

        self::assertCount(2, $recent);
        self::assertSame(['password', 'github'], [$recent[0]->method(), $recent[1]->method()]);
        self::assertSame('203.0.113.7', $recent[0]->ip());
        self::assertSame('Mozilla/5.0', $recent[0]->userAgent());
        self::assertNull($recent[1]->ip());
        self::assertNull($recent[1]->userAgent());
    }

    #[Test]
    public function itReturnsLatestSignInPerUser(): void
    {
        $userA = Uuid::uuid7()->toString();
        $userB = Uuid::uuid7()->toString();

        $this->saveSignIn($userA, 'password', 5);
        $expected = $this->saveSignIn($userA, 'github', 1);

        $latest = $this->repository()->lastSignInAtByUserUuids([$userA, $userB]);

        self::assertSame([$userA], array_keys($latest));
        self::assertSame(
            $expected->toDateTimeString(),
            $latest[$userA]->setTimezone('UTC')->toDateTimeString(),
        );
    }

    #[Test]
    public function itReturnsNothingForNoUsers(): void
    {
        self::assertSame([], $this->repository()->lastSignInAtByUserUuids([]));
    }

    #[Test]
    public function itDeletesOnlyRowsOlderThanTheThreshold(): void
    {
        $user = Uuid::uuid7()->toString();

        $this->saveSignIn($user, 'password', 91);
        $this->saveSignIn($user, 'password', 89);

        self::assertSame(1, $this->repository()->deleteOlderThan(Chronos::now('UTC')->subDays(90)));
        self::assertCount(1, $this->repository()->recentByUserUuid($user, 10));
    }

    private function repository(): UserSignInRepositoryInterface
    {
        $repository = $this->getService(UserSignInRepositoryInterface::class);
        self::assertInstanceOf(UserSignInRepositoryInterface::class, $repository);

        return $repository;
    }

    private function saveSignIn(
        string $userUuid,
        string $method,
        int $daysAgo,
        ?string $ip = null,
        ?string $userAgent = null
    ): Chronos {
        $signIn = new UserSignIn($userUuid, $method, $ip, $userAgent);
        $this->repository()->save($signIn);
        $createdAt = Chronos::now('UTC')->subDays($daysAgo);

        // The ORM stamps created_at with the real clock, so set it directly.
        $this->database()->update('user_sign_ins')->values([
            'created_at' => $createdAt->toDateTimeString(),
        ])->where('uuid', $signIn->getIdentifier()->toString())->run();

        return $createdAt;
    }

    private function database(): DatabaseInterface
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        return $databaseProvider->database();
    }
}
