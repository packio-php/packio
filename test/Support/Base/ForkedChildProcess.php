<?php

declare(strict_types=1);

namespace PackioTest\Support\Base;

use const FILE_APPEND;
use const STREAM_IPPROTO_IP;
use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Throwable;

use function file_get_contents;
use function file_put_contents;
use function is_string;
use function pcntl_waitpid;
use function pcntl_wexitstatus;
use function pcntl_wifexited;
use function sprintf;
use function stream_socket_pair;

/**
 * Helpers shared by tests that fork child processes.
 *
 * @phpstan-require-extends IntegrationTestCase
 */
trait ForkedChildProcess
{
    /** @return array{0: resource, 1: resource} */
    private function socketPair(): array
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($sockets);
        self::assertCount(2, $sockets);

        return [$sockets[0], $sockets[1]];
    }

    private function disconnectDatabaseBeforeFork(): void
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        $database = $databaseProvider->database();
        $database->getDriver(DatabaseInterface::READ)->disconnect();
        $database->getDriver(DatabaseInterface::WRITE)->disconnect();
        self::disconnectPostgresCleanupConnectionBeforeFork();
    }

    private function assertChildExitedSuccessfully(int $pid, string $errorPath): void
    {
        $status = 0;
        pcntl_waitpid($pid, $status);

        self::assertTrue(pcntl_wifexited($status), $this->childErrors($errorPath));
        self::assertSame(0, pcntl_wexitstatus($status), $this->childErrors($errorPath));
    }

    private function recordChildError(string $errorPath, Throwable $throwable): void
    {
        file_put_contents(
            $errorPath,
            sprintf("%s: %s\n", $throwable::class, $throwable->getMessage()),
            FILE_APPEND,
        );
    }

    private function childErrors(string $errorPath): string
    {
        $contents = file_get_contents($errorPath);

        return is_string($contents) && '' !== $contents
            ? $contents
            : 'Child process failed without error output.';
    }
}
