<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Authentication;

use Cake\Chronos\Chronos;
use Packio\Identity\Api\Authentication\ApiAccessTokenContract;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Infrastructure\Authentication\DatabaseUserActorProvider;
use Packio\Identity\Infrastructure\Authentication\UserActor;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Fake\IdentityObjectMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Sirix\Mezzio\Authentication\Token\AuthToken;

#[CoversClass(DatabaseUserActorProvider::class)]
#[CoversClass(UserActor::class)]
#[AllowMockObjectsWithoutExpectations]
final class DatabaseUserActorProviderTest extends TestCase
{
    private const string USER_ID = '00000000-0000-0000-0000-000000000001';

    #[Test]
    public function itReturnsCurrentRolesFromDatabaseAndIgnoresTokenRoles(): void
    {
        $user = $this->user('admin@example.test', 'Admin User', ['admin']);

        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findById')
            ->with(self::USER_ID)
            ->willReturn($user)
        ;

        $databaseUserActorProvider = new DatabaseUserActorProvider($repository, IdentityObjectMapper::create());
        $actor                     = $databaseUserActorProvider->getActor(new AuthToken('token-1', 'session', [
            'user_id'                 => self::USER_ID,
            'roles'                   => ['user'],
            'credentials_fingerprint' => $user->credentialsFingerprint(),
        ]));

        self::assertInstanceOf(UserActor::class, $actor);
        self::assertSame(['admin'], $actor->getRoles());

        $currentUser = $actor->currentUser();
        self::assertInstanceOf(UserDto::class, $currentUser);
        self::assertSame(self::USER_ID, $currentUser->id);
        self::assertSame('admin@example.test', $currentUser->email);
    }

    #[Test]
    public function itReturnsNullWhenUserIdIsMissingOrMalformed(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::never())->method('findById');

        $databaseUserActorProvider = new DatabaseUserActorProvider($repository, IdentityObjectMapper::create());

        self::assertNull($databaseUserActorProvider->getActor(new AuthToken('token-1', 'session', [])));
        self::assertNull($databaseUserActorProvider->getActor(new AuthToken('token-2', 'session', [
            'user_id' => '',
        ])));
        self::assertNull($databaseUserActorProvider->getActor(new AuthToken('token-3', 'session', [
            'user_id' => 123,
        ])));
    }

    #[Test]
    public function itReturnsNullForUnknownUser(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findById')
            ->with('missing-user')
            ->willReturn(null)
        ;

        $databaseUserActorProvider = new DatabaseUserActorProvider($repository, IdentityObjectMapper::create());

        self::assertNull($databaseUserActorProvider->getActor(new AuthToken('token-1', 'session', [
            'user_id' => 'missing-user',
            'roles'   => ['admin'],
        ])));
    }

    #[Test]
    public function itReflectsChangedRepositoryRolesAcrossRequests(): void
    {
        $user = $this->user('member@example.test', 'Member User', ['user']);

        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::exactly(2))
            ->method('findById')
            ->with(self::USER_ID)
            ->willReturnCallback(static fn (): User => $user)
        ;

        $databaseUserActorProvider = new DatabaseUserActorProvider($repository, IdentityObjectMapper::create());
        $authToken                 = new AuthToken('token-1', 'session', [
            'user_id'                 => self::USER_ID,
            'credentials_fingerprint' => $user->credentialsFingerprint(),
        ]);

        $actor = $databaseUserActorProvider->getActor($authToken);
        self::assertInstanceOf(UserActor::class, $actor);
        self::assertSame(['user'], $actor->getRoles());

        $user->replaceRoles(['admin']);

        $actor = $databaseUserActorProvider->getActor($authToken);
        self::assertInstanceOf(UserActor::class, $actor);
        self::assertSame(['admin'], $actor->getRoles());
    }

    #[Test]
    public function staleSessionFingerprintIsRejected(): void
    {
        $user       = $this->user('member@example.test', 'Member User', ['user']);
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('findById')->willReturn($user);

        $databaseUserActorProvider = new DatabaseUserActorProvider($repository, IdentityObjectMapper::create());
        $fingerprint               = $user->credentialsFingerprint();

        self::assertInstanceOf(UserActor::class, $databaseUserActorProvider->getActor(new AuthToken('token-1', 'session', [
            'user_id'                 => self::USER_ID,
            'credentials_fingerprint' => $fingerprint,
        ])));
        self::assertNull($databaseUserActorProvider->getActor(new AuthToken('token-2', 'session', [
            'user_id' => self::USER_ID,
        ])));

        $user->updatePasswordHash('new-hash');

        self::assertNull($databaseUserActorProvider->getActor(new AuthToken('token-3', 'session', [
            'user_id'                 => self::USER_ID,
            'credentials_fingerprint' => $fingerprint,
        ])));
    }

    #[Test]
    public function apiTokenIsNotCheckedForFingerprint(): void
    {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('findById')->willReturn($this->user('api@example.test', 'Api User', ['user']));

        $databaseUserActorProvider = new DatabaseUserActorProvider($repository, IdentityObjectMapper::create());

        self::assertInstanceOf(UserActor::class, $databaseUserActorProvider->getActor(new AuthToken('token-1', ApiAccessTokenContract::STORAGE, [
            'user_id' => self::USER_ID,
        ])));
    }

    /** @param list<string> $roles */
    private function user(string $email, string $displayName, array $roles): User
    {
        $user = new User($email, $displayName, 'hash', $roles);
        $user->setIdentifier(Uuid::fromString(self::USER_ID));
        $user->setCreatedAt(Chronos::parse('2026-01-01T00:00:00+00:00'));

        return $user;
    }
}
