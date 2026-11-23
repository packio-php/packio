<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\Cli;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Identity\Domain\Entity\UserSignIn;
use Packio\Identity\Domain\Repository\UserSignInRepositoryInterface;
use Packio\Identity\Infrastructure\Cli\SignInHistoryPruneCommand;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SignInHistoryPruneCommandTest extends IntegrationTestCase
{
    #[Test]
    public function itDeletesOnlyEntriesOlderThanTheConfiguredRetention(): void
    {
        $user = Uuid::uuid7()->toString();
        $this->saveSignIn($user, 91);
        $this->saveSignIn($user, 89);

        $commandTester = new CommandTester($this->getService(SignInHistoryPruneCommand::class));
        self::assertSame(Command::SUCCESS, $commandTester->execute([]));

        self::assertStringContainsString('older_than_days=90 deleted=1', $commandTester->getDisplay());
        self::assertCount(1, $this->repository()->recentByUserUuid($user, 10));
    }

    #[Test]
    public function itRejectsANonPositiveRetention(): void
    {
        $user = Uuid::uuid7()->toString();
        $this->saveSignIn($user, 400);

        $command = new SignInHistoryPruneCommand($this->repository(), [
            'sign_in_history' => [
                'retention_days' => '0',
            ],
        ]);
        $commandTester = new CommandTester($command);

        self::assertSame(Command::INVALID, $commandTester->execute([]));
        self::assertStringContainsString('SIGN_IN_HISTORY_RETENTION_DAYS must be a positive integer.', $commandTester->getDisplay());
        self::assertCount(1, $this->repository()->recentByUserUuid($user, 10));
    }

    private function repository(): UserSignInRepositoryInterface
    {
        $repository = $this->getService(UserSignInRepositoryInterface::class);
        self::assertInstanceOf(UserSignInRepositoryInterface::class, $repository);

        return $repository;
    }

    private function saveSignIn(string $userUuid, int $daysAgo): void
    {
        $signIn = new UserSignIn(userUuid: $userUuid, method: 'password');
        $this->repository()->save($signIn);

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
