<?php

declare(strict_types=1);

namespace PackioTest\Unit\Identity\Infrastructure\Cli;

use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Infrastructure\Cli\MakeUserAdminCommand;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(MakeUserAdminCommand::class)]
#[AllowMockObjectsWithoutExpectations]
final class MakeUserAdminCommandTest extends TestCase
{
    #[Test]
    public function itAddsAdminRoleToExistingUser(): void
    {
        $user = new User('member@example.test', 'Member User', 'hash', ['user']);

        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findByEmail')
            ->with('member@example.test')
            ->willReturn($user)
        ;
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (User $savedUser): bool => ['user', 'admin'] === $savedUser->roles()))
            ->willReturn($user)
        ;

        $bufferedOutput = new BufferedOutput();
        $exitCode       = new MakeUserAdminCommand($repository)->run(new ArrayInput([
            'email' => 'member@example.test',
        ]), $bufferedOutput);

        self::assertSame(0, $exitCode);
        self::assertSame(['user', 'admin'], $user->roles());
        self::assertStringContainsString('User "member@example.test" is now an admin.', $bufferedOutput->fetch());
    }

    #[Test]
    public function itDoesNotSaveUserWhoIsAlreadyAdmin(): void
    {
        $user = new User('admin@example.test', 'Admin User', 'hash', ['admin']);

        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findByEmail')
            ->with('admin@example.test')
            ->willReturn($user)
        ;
        $repository->expects(self::never())->method('save');

        $bufferedOutput = new BufferedOutput();
        $exitCode       = new MakeUserAdminCommand($repository)->run(new ArrayInput([
            'email' => 'admin@example.test',
        ]), $bufferedOutput);

        self::assertSame(0, $exitCode);
        self::assertSame(['admin'], $user->roles());
        self::assertStringContainsString('User "admin@example.test" is already an admin.', $bufferedOutput->fetch());
    }

    #[Test]
    public function itFailsWhenUserDoesNotExist(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findByEmail')
            ->with('missing@example.test')
            ->willReturn(null)
        ;
        $repository->expects(self::never())->method('save');

        $bufferedOutput = new BufferedOutput();
        $exitCode       = new MakeUserAdminCommand($repository)->run(new ArrayInput([
            'email' => 'missing@example.test',
        ]), $bufferedOutput);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('User "missing@example.test" was not found.', $bufferedOutput->fetch());
    }
}
