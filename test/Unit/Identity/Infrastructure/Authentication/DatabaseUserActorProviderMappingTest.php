<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Infrastructure\Authentication;

use const DATE_ATOM;

use Cake\Chronos\Chronos;
use Packio\Identity\Domain\Entity\User;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Fake\IdentityObjectMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class DatabaseUserActorProviderMappingTest extends TestCase
{
    #[Test]
    public function itMatchesManualMappingForUserWithoutPasswordOrUpdateOrVerifiedEmail(): void
    {
        $user = new User('member@example.test', 'Member', '');
        $user->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000001'));
        $user->setCreatedAt(new Chronos('2026-01-02 03:04:05'));

        self::assertEquals($this->manual($user), IdentityObjectMapper::create()->map($user, UserDto::class));
    }

    #[Test]
    public function itMatchesManualMappingForBannedUser(): void
    {
        $user = new User('banned@example.test', 'Banned', 'hash', ['user'], new Chronos('2026-01-03 00:00:00'));
        $user->setIdentifier(Uuid::fromString('00000000-0000-0000-0000-000000000002'));
        $user->setCreatedAt(new Chronos('2026-01-02 03:04:05'));
        $user->ban('Spam', '00000000-0000-0000-0000-000000000009');

        self::assertEquals($this->manual($user), IdentityObjectMapper::create()->map($user, UserDto::class));
    }

    private function manual(User $user): UserDto
    {
        return new UserDto(
            id: $user->getIdentifier()->toString(),
            email: $user->email(),
            displayName: $user->displayName(),
            roles: $user->roles(),
            createdAt: $user->getCreatedAt()->format(DATE_ATOM),
            updatedAt: $user->getUpdatedAt()?->format(DATE_ATOM) ?? '',
            hasPassword: '' !== $user->passwordHash(),
            isEmailVerified: $user->emailVerifiedAt() instanceof Chronos,
            isBanned: $user->isBanned(),
            banReason: $user->banReason(),
            bannedAt: $user->bannedAt()?->format(DATE_ATOM),
            bannedByUserId: $user->bannedByUserId(),
        );
    }
}
