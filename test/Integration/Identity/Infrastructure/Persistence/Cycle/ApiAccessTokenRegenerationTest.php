<?php

declare(strict_types=1);

namespace PackioTest\Integration\Identity\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Identity\Application\Command\ApiAccessTokenCommandService;
use Packio\Identity\Application\Result\ApiAccessTokenRegeneration;
use Packio\Identity\Domain\Entity\User;
use Packio\Identity\Domain\Repository\ApiAccessTokenRepositoryInterface;
use Packio\Identity\Domain\Repository\UserRepositoryInterface;
use PackioTest\Support\Base\ForkedChildProcess;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Throwable;

use function array_filter;
use function array_values;
use function bin2hex;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function fread;
use function function_exists;
use function fwrite;
use function hash;
use function password_hash;
use function pcntl_fork;
use function pcntl_waitpid;
use function pcntl_wexitstatus;
use function pcntl_wifexited;
use function random_bytes;
use function sprintf;
use function stream_select;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class ApiAccessTokenRegenerationTest extends IntegrationTestCase
{
    use ForkedChildProcess;

    #[Test]
    public function itRegeneratesATokenAtomically(): void
    {
        $userId = $this->createUser();

        $commandService               = $this->commandService();
        $issuedApiAccessToken         = $commandService->issueForUser($userId, 'Deploy token', ['read', 'write']);
        $tokenId                      = $issuedApiAccessToken->token->id;

        $apiAccessTokenRegeneration = $commandService->regenerateForUser($userId, $tokenId);

        self::assertTrue($apiAccessTokenRegeneration->success);
        self::assertSame($tokenId, $apiAccessTokenRegeneration->revokedId);
        self::assertNotNull($apiAccessTokenRegeneration->issued);
        self::assertSame('Deploy token', $apiAccessTokenRegeneration->issued->token->name);
        self::assertSame(['read', 'write'], $apiAccessTokenRegeneration->issued->token->scopes);
        self::assertSame($issuedApiAccessToken->token->expiresAt, $apiAccessTokenRegeneration->issued->token->expiresAt);
        self::assertNotSame($tokenId, $apiAccessTokenRegeneration->issued->token->id);
        self::assertSame('', $apiAccessTokenRegeneration->issued->token->revokedAt);

        $repository = $this->repository();
        $revoked    = $repository->findByIdForUser($tokenId, $userId);
        self::assertNotNull($revoked);
        self::assertTrue($revoked->isRevoked());

        $replacement = $repository->findByTokenHash(hash('sha256', $apiAccessTokenRegeneration->issued->plainTextToken));
        self::assertNull($replacement, 'API access tokens are HMAC-hashed, a sha256 lookup must not find the replacement.');
    }

    #[Test]
    public function itKeepsTheOldTokenWhenTheReplacementWriteFails(): void
    {
        $userId = $this->createUser();

        $commandService               = $this->commandService();
        $issuedApiAccessToken         = $commandService->issueForUser($userId, 'Deploy token', ['read']);
        $tokenId                      = $issuedApiAccessToken->token->id;

        $this->failNextApiAccessTokenInsert();

        $apiAccessTokenRegeneration = $commandService->regenerateForUser($userId, $tokenId);

        self::assertFalse($apiAccessTokenRegeneration->success);
        self::assertTrue($apiAccessTokenRegeneration->isPersistenceFailed());

        $this->clearFailingApiAccessTokenInsert();

        $repository = $this->repository();
        $existing   = $repository->findByIdForUser($tokenId, $userId);
        self::assertNotNull($existing, 'The old token must survive a failed regeneration.');
        self::assertFalse($existing->isRevoked(), 'The old token must remain usable after a failed regeneration.');
        self::assertCount(1, $repository->latestForUser($userId));
    }

    #[Test]
    public function itRollsBackTheReplacementWhenTheRevokeWriteFails(): void
    {
        $userId = $this->createUser();

        $commandService               = $this->commandService();
        $issuedApiAccessToken         = $commandService->issueForUser($userId, 'Deploy token', ['read']);
        $tokenId                      = $issuedApiAccessToken->token->id;

        // The replacement INSERT succeeds; failing the UPDATE that stamps
        // revoked_at exercises the partial-write rollback path: without the
        // shared transaction the replacement would stay active.
        $this->failApiAccessTokenRevokeUpdate();

        $apiAccessTokenRegeneration = $commandService->regenerateForUser($userId, $tokenId);

        self::assertFalse($apiAccessTokenRegeneration->success);
        self::assertTrue($apiAccessTokenRegeneration->isPersistenceFailed());

        $this->clearFailingApiAccessTokenRevokeUpdate();

        $repository = $this->repository();
        $existing   = $repository->findByIdForUser($tokenId, $userId);
        self::assertNotNull($existing, 'The old token must survive a failed revoke.');
        self::assertFalse($existing->isRevoked(), 'The old token must remain usable after a failed revoke.');
        self::assertCount(1, $repository->latestForUser($userId), 'The replacement must have been rolled back.');

        // The old token must still work end to end: regenerate again successfully.
        $retry = $commandService->regenerateForUser($userId, $tokenId);
        self::assertTrue($retry->success);
        self::assertSame($tokenId, $retry->revokedId);
    }

    #[Test]
    public function concurrentRegenerationsProduceExactlyOneReplacement(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $userId = $this->createUser();

        $commandService       = $this->commandService();
        $issuedApiAccessToken = $commandService->issueForUser($userId, 'Deploy token', ['read']);
        $tokenId              = $issuedApiAccessToken->token->id;

        $this->disconnectPostgresCleanupConnectionBeforeFork();
        $database = $this->database();
        $database->getDriver(DatabaseInterface::READ)->disconnect();
        $database->getDriver(DatabaseInterface::WRITE)->disconnect();
        $errorPath = tempnam(sys_get_temp_dir(), 'packio-api-token-regen-');
        self::assertIsString($errorPath);

        $lockedSockets  = $this->socketPair();
        $releaseSockets = $this->socketPair();
        $savedSockets   = $this->socketPair();
        $resultSockets  = $this->socketPair();

        $lockHolderPid = $this->forkLockHolder($userId, $tokenId, $lockedSockets[1], $releaseSockets[1], $savedSockets[1], $errorPath);
        fclose($lockedSockets[1]);
        fclose($releaseSockets[1]);
        fclose($savedSockets[1]);

        // The lock holder has taken the advisory lock and now waits for the
        // start signal to run its regeneration inside that transaction.
        self::assertSame('1', fread($lockedSockets[0], 1));
        fclose($lockedSockets[0]);

        $waiterPid = $this->forkBlockedRegeneration($userId, $tokenId, $resultSockets[1], $errorPath);
        fclose($resultSockets[1]);

        // While the lock is held, the second regeneration must not have
        // completed (it is waiting on the advisory lock).
        $read   = [$resultSockets[0]];
        $write  = null;
        $except = null;
        $ready  = stream_select($read, $write, $except, 0, 300_000);
        self::assertSame(0, $ready, 'The second regeneration completed while the first worker still held the advisory lock.');

        // Resume the lock holder: it regenerates (and revokes the old token)
        // while still holding the transaction-level advisory lock, then commits.
        fwrite($releaseSockets[0], '1');
        fclose($releaseSockets[0]);
        self::assertSame('1', fread($savedSockets[0], 1));
        fclose($savedSockets[0]);

        // The lock is gone; the second regeneration must now observe the
        // already-revoked token and fail with not_found ('0'), specifically
        // not with persistence_failed ('2').
        $result = fread($resultSockets[0], 1);
        fclose($resultSockets[0]);

        $this->assertChildExitedSuccessfully($lockHolderPid, $errorPath);
        $this->assertChildExitedSuccessfully($waiterPid, $errorPath);
        self::assertSame('0', $result, 'The second concurrent regeneration must observe the revoked token as not_found.');

        // The parent heap still holds the pre-fork entity of the old token,
        // so the final state is asserted via a fresh database read.
        $rows = $this->database()
            ->select('revoked_at')
            ->from('api_access_tokens')
            ->where('user_id', $userId)
            ->fetchAll()
        ;
        self::assertCount(2, $rows, 'Exactly one replacement must exist.');
        $active = array_values(array_filter(
            $rows,
            static fn (array $row): bool => null === $row['revoked_at'],
        ));
        self::assertCount(1, $active, 'Exactly one active token must remain.');
        @unlink($errorPath);
    }

    #[Test]
    public function itReturnsNotFoundForForeignRevokedOrUnknownTokens(): void
    {
        $userId  = $this->createUser();
        $otherId = $this->createUser();

        $commandService = $this->commandService();

        $issuedApiAccessToken      = $commandService->issueForUser($otherId, 'Other user token', ['read']);
        $regeneration              = $commandService->regenerateForUser($userId, $issuedApiAccessToken->token->id);
        self::assertFalse($regeneration->success);
        self::assertTrue($regeneration->isNotFound());

        $revoked = $commandService->issueForUser($userId, 'Revoked token', ['read']);
        self::assertTrue($commandService->revokeForUser($userId, $revoked->token->id));
        $regeneration = $commandService->regenerateForUser($userId, $revoked->token->id);
        self::assertFalse($regeneration->success);
        self::assertTrue($regeneration->isNotFound());

        $regeneration = $commandService->regenerateForUser($userId, Uuid::uuid7()->toString());
        self::assertFalse($regeneration->success);
        self::assertTrue($regeneration->isNotFound());
    }

    private function createUser(): string
    {
        $userRepository = $this->getService(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $userRepository);

        $suffix = bin2hex(random_bytes(6));
        $user   = $userRepository->save(User::register(
            email: 'api-token-regen-' . $suffix . '@example.test',
            displayName: 'Regeneration Test User ' . $suffix,
            passwordHash: password_hash('Password1!', PASSWORD_DEFAULT),
            roles: ['user'],
        ));

        return $user->getIdentifier()->toString();
    }

    private function commandService(): ApiAccessTokenCommandService
    {
        $commandService = $this->getService(ApiAccessTokenCommandService::class);
        self::assertInstanceOf(ApiAccessTokenCommandService::class, $commandService);

        return $commandService;
    }

    private function repository(): ApiAccessTokenRepositoryInterface
    {
        $repository = $this->getService(ApiAccessTokenRepositoryInterface::class);
        self::assertInstanceOf(ApiAccessTokenRepositoryInterface::class, $repository);

        return $repository;
    }

    private function failNextApiAccessTokenInsert(): void
    {
        $this->database()->execute(
            <<<'SQL'
                CREATE OR REPLACE FUNCTION test_fail_api_access_token_insert() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Simulated api_access_tokens insert failure';
                END;
                $$ LANGUAGE plpgsql
                SQL,
        );
        $this->database()->execute(
            'CREATE TRIGGER test_fail_api_access_token_insert_trigger '
            . 'BEFORE INSERT ON api_access_tokens '
            . 'FOR EACH ROW EXECUTE FUNCTION test_fail_api_access_token_insert()',
        );
    }

    private function clearFailingApiAccessTokenInsert(): void
    {
        $this->database()->execute('DROP TRIGGER IF EXISTS test_fail_api_access_token_insert_trigger ON api_access_tokens');
        $this->database()->execute('DROP FUNCTION IF EXISTS test_fail_api_access_token_insert()');
    }

    private function failApiAccessTokenRevokeUpdate(): void
    {
        $this->database()->execute(
            <<<'SQL'
                CREATE OR REPLACE FUNCTION test_fail_api_access_token_revoke() RETURNS trigger AS $$
                BEGIN
                    IF NEW.revoked_at IS NOT NULL THEN
                        RAISE EXCEPTION 'Simulated api_access_tokens revoke failure';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                SQL,
        );
        $this->database()->execute(
            'CREATE TRIGGER test_fail_api_access_token_revoke_trigger '
            . 'BEFORE UPDATE OF revoked_at ON api_access_tokens '
            . 'FOR EACH ROW EXECUTE FUNCTION test_fail_api_access_token_revoke()',
        );
    }

    private function clearFailingApiAccessTokenRevokeUpdate(): void
    {
        $this->database()->execute('DROP TRIGGER IF EXISTS test_fail_api_access_token_revoke_trigger ON api_access_tokens');
        $this->database()->execute('DROP FUNCTION IF EXISTS test_fail_api_access_token_revoke()');
    }

    private function database(): DatabaseInterface
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        return $databaseProvider->database('default');
    }

    /**
     * Forks a child that takes the advisory lock, signals it is held, then
     * regenerates the token inside that transaction on the parent's signal.
     */
    private function forkLockHolder(
        string $userId,
        string $tokenId,
        mixed $lockedSocket,
        mixed $releaseSocket,
        mixed $savedSocket,
        string $errorPath,
    ): int {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);

        if (0 === $pid) {
            try {
                $this->holdApiAccessTokenLock($userId, $tokenId, $lockedSocket, $releaseSocket, $savedSocket);

                exit(0);
            } catch (Throwable $throwable) {
                file_put_contents($errorPath, sprintf('%s: %s\n', $throwable::class, $throwable->getMessage()), FILE_APPEND);

                exit(1);
            }
        }

        return $pid;
    }

    private function holdApiAccessTokenLock(
        string $userId,
        string $tokenId,
        mixed $lockedSocket,
        mixed $releaseSocket,
        mixed $savedSocket,
    ): void {
        $apiAccessTokenRegeneration = null;
        $database                   = $this->database();
        $database->transaction(function(DatabaseInterface $transactionDatabase) use ($userId, $tokenId, $lockedSocket, $releaseSocket, &$apiAccessTokenRegeneration): void {
            $transactionDatabase->query('SELECT pg_advisory_xact_lock(hashtext(?))', ['identity-api-access-token:' . $userId]);
            fwrite($lockedSocket, '1');
            fclose($lockedSocket);

            // Hold the lock until the parent resumes us, then run the first
            // regeneration inside the same transaction so its revoke becomes
            // visible to any waiter only together with the lock release.
            @fread($releaseSocket, 1);
            fclose($releaseSocket);

            $apiAccessTokenRegeneration = $this->commandService()->regenerateForUser($userId, $tokenId);
            if (! $apiAccessTokenRegeneration->success) {
                file_put_contents('php://stderr', "[regen-holder] regeneration failed unexpectedly\n");

                exit(1);
            }
        });
        // Signal only after the transaction has committed: any state the parent
        // reads from here on reflects the regeneration.
        fwrite($savedSocket, $apiAccessTokenRegeneration instanceof ApiAccessTokenRegeneration ? '1' : '0');
        fclose($savedSocket);
    }

    /**
     * Forks a child that runs the second concurrent regeneration and reports
     * the outcome over the result socket: '1' when a replacement was issued,
     * '0' when the regeneration returned not_found.
     */
    private function forkBlockedRegeneration(
        string $userId,
        string $tokenId,
        mixed $resultSocket,
        string $errorPath,
    ): int {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);

        if (0 === $pid) {
            try {
                $apiAccessTokenRegeneration = $this->commandService()->regenerateForUser($userId, $tokenId);
                // '1' = regenerated, '0' = not_found, '2' = persistence_failed.
                fwrite($resultSocket, match (true) {
                    $apiAccessTokenRegeneration->success               => '1',
                    $apiAccessTokenRegeneration->isPersistenceFailed() => '2',
                    default                                            => '0',
                });
                fclose($resultSocket);

                exit(0);
            } catch (Throwable $throwable) {
                file_put_contents($errorPath, sprintf('%s: %s\n', $throwable::class, $throwable->getMessage()), FILE_APPEND);

                exit(1);
            }
        }

        return $pid;
    }

    private function assertChildExitedSuccessfully(int $pid, string $errorPath): void
    {
        $status = 0;
        pcntl_waitpid($pid, $status);

        $errors = file_get_contents($errorPath);
        self::assertTrue(pcntl_wifexited($status), 'The forked regeneration exited abnormally. ' . ($errors ?: ''));
        self::assertSame(0, pcntl_wexitstatus($status), 'The forked regeneration failed. ' . ($errors ?: ''));
    }
}
