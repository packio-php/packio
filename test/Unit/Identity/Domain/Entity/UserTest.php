<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Domain\Entity;

use Cake\Chronos\Chronos;
use InvalidArgumentException;
use Packio\Identity\Api\UserBanReason;
use Packio\Identity\Domain\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    #[Test]
    public function itStoresNormalizedBanState(): void
    {
        $user = new User('member@example.test', 'Member User');

        $user->ban('  Violation of project rules.  ', '  administrator-id  ');

        self::assertTrue($user->isBanned());
        self::assertInstanceOf(Chronos::class, $user->bannedAt());
        self::assertSame('Violation of project rules.', $user->banReason());
        self::assertSame('administrator-id', $user->bannedByUserId());
    }

    #[Test]
    public function itClearsEveryBanStateFieldWhenUnbanned(): void
    {
        $user = new User('member@example.test', 'Member User');
        $user->ban('Violation of project rules.', 'administrator-id');

        $user->unban();

        self::assertFalse($user->isBanned());
        self::assertNull($user->bannedAt());
        self::assertNull($user->banReason());
        self::assertNull($user->bannedByUserId());
    }

    #[Test]
    public function itRejectsBlankBanReasonsWithoutChangingState(): void
    {
        $user = new User('member@example.test', 'Member User');

        try {
            $user->ban('  ', 'administrator-id');
            self::fail('Expected a blank ban reason to be rejected.');
        } catch (InvalidArgumentException) {
            self::assertFalse($user->isBanned());
        }
    }

    #[Test]
    public function itRejectsBanReasonsLongerThanThePersistenceLimit(): void
    {
        $user = new User('member@example.test', 'Member User');

        $this->expectException(InvalidArgumentException::class);
        $user->ban(str_repeat('a', UserBanReason::MAX_LENGTH + 1), 'administrator-id');
    }

    #[Test]
    public function itRejectsAnEmptyBanningActorId(): void
    {
        $user = new User('member@example.test', 'Member User');

        $this->expectException(InvalidArgumentException::class);
        $user->ban('Violation of project rules.', '  ');
    }
}
