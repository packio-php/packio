<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Persistence\Cycle;

use const STREAM_IPPROTO_IP;
use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;

use Cake\Chronos\Chronos;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceOAuthAccountRepository;
use PackioTest\Support\Base\ForkedChildProcess;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

use function fclose;
use function fread;
use function function_exists;
use function fwrite;
use function pcntl_fork;
use function stream_select;
use function stream_socket_pair;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[Group('slow')]
final class SourceOAuthAccountRepositoryLockingTest extends IntegrationTestCase
{
    use ForkedChildProcess;

    #[Test]
    public function withLockedByIdBlocksConcurrentCallbackUntilFirstTransactionCommits(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $accountId = $this->persistOAuthAccount();
        $this->disconnectDatabaseBeforeFork();

        $errorPath = tempnam(sys_get_temp_dir(), 'packio-source-oauth-lock-errors-');
        self::assertIsString($errorPath);

        $lockedSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($lockedSockets);
        $releaseSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($releaseSockets);
        $enteredSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($enteredSockets);

        $lockHolderPid = $this->forkLockHolder($accountId, $lockedSockets[1], $releaseSockets[1], $errorPath);
        fclose($lockedSockets[1]);
        fclose($releaseSockets[1]);

        self::assertSame('1', fread($lockedSockets[0], 1));
        fclose($lockedSockets[0]);

        $blockedPid = $this->forkBlockedLockWaiter($accountId, $enteredSockets[1], $errorPath);
        fclose($enteredSockets[1]);

        $read   = [$enteredSockets[0]];
        $write  = null;
        $except = null;
        $ready  = stream_select($read, $write, $except, 0, 300_000);
        self::assertSame(0, $ready, 'Concurrent lock waiter entered callback before the first transaction committed.');

        fwrite($releaseSockets[0], '1');
        fclose($releaseSockets[0]);

        self::assertSame('1', fread($enteredSockets[0], 1));
        fclose($enteredSockets[0]);

        $this->assertChildExitedSuccessfully($lockHolderPid, $errorPath);
        $this->assertChildExitedSuccessfully($blockedPid, $errorPath);

        @unlink($errorPath);
    }

    private function persistOAuthAccount(): string
    {
        $repository = $this->getService(SourceOAuthAccountRepository::class);
        self::assertInstanceOf(SourceOAuthAccountRepository::class, $repository);

        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'codeberg',
            providerUserId: 'codeberg-user-1',
            accessToken: 'enc-access-token',
            refreshToken: 'enc-refresh-token',
            tokenExpiresAt: Chronos::parse('-1 hour'),
        );
        $sourceOAuthAccount->setIdentifier(Uuid::uuid7());
        $repository->save($sourceOAuthAccount);

        return $sourceOAuthAccount->getIdentifier()->toString();
    }

    /**
     * @param resource $lockedSocket
     * @param resource $releaseSocket
     */
    private function forkLockHolder(string $accountId, mixed $lockedSocket, mixed $releaseSocket, string $errorPath): int
    {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);

        if (0 === $pid) {
            $this->holdOAuthAccountLockInChild($accountId, $lockedSocket, $releaseSocket, $errorPath);
        }

        return $pid;
    }

    /**
     * @param resource $enteredSocket
     */
    private function forkBlockedLockWaiter(string $accountId, mixed $enteredSocket, string $errorPath): int
    {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);

        if (0 === $pid) {
            $this->waitForOAuthAccountLockInChild($accountId, $enteredSocket, $errorPath);
        }

        return $pid;
    }

    /**
     * @param resource $lockedSocket
     * @param resource $releaseSocket
     */
    private function holdOAuthAccountLockInChild(string $accountId, mixed $lockedSocket, mixed $releaseSocket, string $errorPath): never
    {
        try {
            $repository = $this->lockedRepository();
            $repository->withLockedById(
                $accountId,
                static function() use ($lockedSocket, $releaseSocket): void {
                    fwrite($lockedSocket, '1');
                    fclose($lockedSocket);

                    @fread($releaseSocket, 1);
                    fclose($releaseSocket);
                },
            );

            exit(0);
        } catch (Throwable $throwable) {
            $this->recordChildError($errorPath, $throwable);

            exit(1);
        }
    }

    /**
     * @param resource $enteredSocket
     */
    private function waitForOAuthAccountLockInChild(string $accountId, mixed $enteredSocket, string $errorPath): never
    {
        try {
            $repository = $this->lockedRepository();
            $repository->withLockedById(
                $accountId,
                static function() use ($enteredSocket): void {
                    fwrite($enteredSocket, '1');
                    fclose($enteredSocket);
                },
            );

            exit(0);
        } catch (Throwable $throwable) {
            $this->recordChildError($errorPath, $throwable);

            exit(1);
        }
    }

    private function lockedRepository(): SourceOAuthAccountRepository
    {
        $repository = $this->getService(SourceOAuthAccountRepository::class);
        if (! $repository instanceof SourceOAuthAccountRepository) {
            throw new RuntimeException('SourceOAuthAccountRepository is not configured.');
        }

        return $repository;
    }
}
