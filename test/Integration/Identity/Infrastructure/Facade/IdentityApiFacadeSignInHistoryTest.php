<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\Facade;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Entity\UserOAuthAccount;
use Packio\Identity\Domain\Entity\UserSignIn;
use Packio\Identity\Domain\Repository\UserOAuthAccountRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\Repository\UserSignInRepositoryInterface;
use Packio\Identity\Domain\ValueObject\OAuthProvider;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function array_keys;

final class IdentityApiFacadeSignInHistoryTest extends IntegrationTestCase
{
    #[Test]
    public function itMapsRecentSignInsNewestFirst(): void
    {
        $user = Uuid::uuid7()->toString();
        $this->saveSignIn($user, 'github', 2, '203.0.113.7', 'Mozilla/5.0');
        $this->saveSignIn($user, 'password', 1);
        $this->saveSignIn($user, 'passkey', 0);

        $signIns = $this->facade()->recentSignIns($user);

        self::assertCount(3, $signIns);
        self::assertSame('passkey', $signIns[0]->method);
        self::assertSame('passkey', $signIns[0]->methodLabel);
        self::assertSame('password', $signIns[1]->method);
        self::assertSame('Password', $signIns[1]->methodLabel);
        self::assertNull($signIns[1]->ip);
        self::assertSame('github', $signIns[2]->method);
        self::assertSame('GitHub', $signIns[2]->methodLabel);
        self::assertSame('203.0.113.7', $signIns[2]->ip);
        self::assertSame('Mozilla/5.0', $signIns[2]->userAgent);
        self::assertSame(Chronos::parse($signIns[2]->occurredAt)->toAtomString(), $signIns[2]->occurredAt);
    }

    #[Test]
    public function itLimitsRecentSignInsToTwentyByDefault(): void
    {
        $user = Uuid::uuid7()->toString();
        for ($i = 0; $i < 21; ++$i) {
            $this->saveSignIn($user, 'password', 0);
        }

        self::assertCount(20, $this->facade()->recentSignIns($user));
    }

    #[Test]
    public function itReturnsLastSignInOnlyForUsersWhoSignedIn(): void
    {
        $a = Uuid::uuid7()->toString();
        $b = Uuid::uuid7()->toString();
        $this->saveSignIn($a, 'password', 1);

        $last = $this->facade()->lastSignInAtByUserIds([$a, $b]);

        self::assertSame([$a], array_keys($last));
        self::assertSame(Chronos::parse($last[$a])->toAtomString(), $last[$a]);
    }

    #[Test]
    public function itMapsOAuthAccountsWithLabels(): void
    {
        $user = $this->container->get(UserRepositoryInterface::class)
            ->save(User::register('oauth-list@example.test', 'Owner', 'hash', ['user']))
        ;
        $userId = $user->getIdentifier()->toString();
        $this->container->get(UserOAuthAccountRepositoryInterface::class)
            ->save(UserOAuthAccount::register($userId, OAuthProvider::Github, '123', null, 'octocat'))
        ;

        $accounts = $this->facade()->oauthAccountsForUser($userId);

        self::assertCount(1, $accounts);
        self::assertSame('github', $accounts[0]->provider);
        self::assertSame('GitHub', $accounts[0]->providerLabel);
        self::assertSame('octocat', $accounts[0]->accountLabel);
        self::assertNull($accounts[0]->providerEmail);
        self::assertSame('octocat', $accounts[0]->providerUsername);
    }

    private function facade(): IdentityApiFacadeInterface
    {
        $facade = $this->getService(IdentityApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $facade);

        return $facade;
    }

    private function saveSignIn(
        string $userUuid,
        string $method,
        int $daysAgo,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        $signIn     = new UserSignIn($userUuid, $method, $ip, $userAgent);
        $repository = $this->getService(UserSignInRepositoryInterface::class);
        self::assertInstanceOf(UserSignInRepositoryInterface::class, $repository);
        $repository->save($signIn);

        // The ORM stamps created_at with the real clock, so set it directly.
        $this->database()->update('user_sign_ins')->values([
            'created_at' => Chronos::now('UTC')->subDays($daysAgo)->toDateTimeString(),
        ])->where('uuid', $signIn->getIdentifier()->toString())->run();
    }

    private function database(): DatabaseInterface
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        return $databaseProvider->database();
    }
}
