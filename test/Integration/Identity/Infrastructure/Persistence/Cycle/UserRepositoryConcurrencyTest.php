<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\Persistence\Cycle;

use const STREAM_IPPROTO_IP;
use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;

use Cycle\Database\DatabaseProviderInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use Packio\Identity\Domain\Value\UserModerationOutcome;
use Packio\Shared\Api\Dto\UserDto;
use PackioTest\Support\Base\ForkedChildProcess;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;

use function bin2hex;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function fread;
use function function_exists;
use function fwrite;
use function is_array;
use function is_string;
use function json_decode;
use function pcntl_fork;
use function pcntl_waitpid;
use function pcntl_wexitstatus;
use function pcntl_wifexited;
use function random_bytes;
use function sort;
use function sprintf;
use function stream_socket_pair;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[Group('slow')]
final class UserRepositoryConcurrencyTest extends IntegrationTestCase
{
    use ForkedChildProcess;

    #[Test]
    public function concurrentBansOfDistinctActiveAdminsLeaveOneActiveAdmin(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $identity);

        $userDto   = $this->createAdmin($identity, 'actor');
        $targetA   = $this->createAdmin($identity, 'target-a');
        $targetB   = $this->createAdmin($identity, 'target-b');

        $this->disconnectDatabaseBeforeFork();
        $errorPath = tempnam(sys_get_temp_dir(), 'packio-user-ban-errors-');
        self::assertIsString($errorPath);

        try {
            $statuses = $this->banUsersConcurrently([
                [
                    'actorId'  => $userDto->id,
                    'targetId' => $targetA->id,
                ],
                [
                    'actorId'  => $userDto->id,
                    'targetId' => $targetB->id,
                ],
            ], $errorPath);
            sort($statuses);

            self::assertSame([0, 0], $statuses, $this->childErrors($errorPath));
            self::assertSame(1, $this->activeAdminCount());
        } finally {
            @unlink($errorPath);
        }
    }

    #[Test]
    public function concurrentBansBetweenTheLastTwoActiveAdminsAllowOnlyOneBan(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $identity = $this->getService(IdentityApiFacadeInterface::class);
        self::assertInstanceOf(IdentityApiFacadeInterface::class, $identity);

        $userDto = $this->createAdmin($identity, 'admin-a');
        $adminB  = $this->createAdmin($identity, 'admin-b');

        $this->disconnectDatabaseBeforeFork();
        $errorPath = tempnam(sys_get_temp_dir(), 'packio-user-ban-errors-');
        self::assertIsString($errorPath);

        try {
            $statuses = $this->banUsersConcurrently([
                [
                    'actorId'  => $userDto->id,
                    'targetId' => $adminB->id,
                ],
                [
                    'actorId'  => $adminB->id,
                    'targetId' => $userDto->id,
                ],
            ], $errorPath);
            sort($statuses);

            self::assertSame([0, 2], $statuses, $this->childErrors($errorPath));
            self::assertSame(1, $this->activeAdminCount());
        } finally {
            @unlink($errorPath);
        }
    }

    private function createAdmin(IdentityApiFacadeInterface $identityApiFacade, string $prefix): UserDto
    {
        return $identityApiFacade->createUser(
            $prefix . '-' . bin2hex(random_bytes(6)) . '@example.test',
            'Concurrent Admin',
            'Password1!',
            ['admin'],
        );
    }

    /**
     * @param list<array{actorId: string, targetId: string}> $operations
     *
     * @return list<int>
     */
    private function banUsersConcurrently(array $operations, string $errorPath): array
    {
        $children = [];

        foreach ($operations as $operation) {
            $releaseSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertIsArray($releaseSockets);
            $readySockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertIsArray($readySockets);

            $pid = pcntl_fork();
            self::assertNotFalse($pid);

            if (0 === $pid) {
                fclose($releaseSockets[0]);
                fclose($readySockets[0]);
                fwrite($readySockets[1], '1');
                fclose($readySockets[1]);
                @fread($releaseSockets[1], 1);
                fclose($releaseSockets[1]);

                $this->banUserInChild($operation['actorId'], $operation['targetId'], $errorPath);
            }

            fclose($releaseSockets[1]);
            fclose($readySockets[1]);
            $children[] = [
                'pid'           => $pid,
                'readySocket'   => $readySockets[0],
                'releaseSocket' => $releaseSockets[0],
            ];
        }

        foreach ($children as $child) {
            self::assertSame('1', fread($child['readySocket'], 1));
            fclose($child['readySocket']);
        }

        foreach ($children as $child) {
            fwrite($child['releaseSocket'], '1');
            fclose($child['releaseSocket']);
        }

        $statuses = [];
        foreach ($children as $child) {
            $status = 0;
            pcntl_waitpid($child['pid'], $status);
            $exitStatus = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : false;
            $statuses[] = false === $exitStatus ? 1 : $exitStatus;
        }

        return $statuses;
    }

    private function banUserInChild(string $actorId, string $targetId, string $errorPath): never
    {
        try {
            $users = $this->getService(UserRepositoryInterface::class);
            if (! $users instanceof UserRepositoryInterface) {
                throw new RuntimeException('User repository is not configured.');
            }

            $outcome = $users->banUser($targetId, $actorId, 'Violation of project rules.');

            exit(match ($outcome) {
                UserModerationOutcome::Success          => 0,
                UserModerationOutcome::LastActiveAdmin  => 2,
                default                                 => 3,
            });
        } catch (Throwable $throwable) {
            file_put_contents($errorPath, sprintf("%s: %s\n", $throwable::class, $throwable->getMessage()), FILE_APPEND);

            exit(1);
        }
    }

    private function activeAdminCount(): int
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        $rows  = $databaseProvider->database()->select('roles')->from('users')->where('banned_at', null)->fetchAll();
        $count = 0;
        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['roles'] ?? null) && ['admin'] === json_decode($row['roles'], true)) {
                ++$count;
            }
        }

        return $count;
    }

    private function childErrors(string $errorPath): string
    {
        return (string) file_get_contents($errorPath);
    }
}
