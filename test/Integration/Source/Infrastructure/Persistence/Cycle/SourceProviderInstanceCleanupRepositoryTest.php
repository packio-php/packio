<?php

declare(strict_types=1);

namespace PackioTest\Integration\Source\Infrastructure\Persistence\Cycle;

use const STREAM_IPPROTO_IP;
use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;

use Cycle\Database\DatabaseInterface;
use Packio\Source\Api\Exception\SourceProviderInstanceException;
use Packio\Source\Application\Command\SourceProviderInstanceService;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Entity\SourceProviderInstance;
use Packio\Source\Domain\Exception\SourceProviderInstanceUnavailableException;
use Packio\Source\Infrastructure\Persistence\Cycle\ProviderAuthorizationRepository;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceOAuthAccountRepository;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceProviderInstanceCleanupRepository;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceProviderInstanceRepository;
use Packio\Source\Infrastructure\Persistence\Cycle\SourceRepository;
use PackioTest\Support\Base\ForkedChildProcess;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Throwable;

use function array_map;
use function fclose;
use function file_put_contents;
use function fread;
use function function_exists;
use function fwrite;
use function pcntl_fork;
use function sprintf;
use function stream_select;
use function stream_socket_pair;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class SourceProviderInstanceCleanupRepositoryTest extends IntegrationTestCase
{
    use ForkedChildProcess;

    #[Test]
    public function deletesOnlyTheSelectedInstancesInactiveDependenciesAtomically(): void
    {
        $organizationId          = Uuid::uuid7()->toString();
        $instance                = $this->instance($organizationId, 'https://gitlab-one.example.test');
        $sourceProviderInstance  = $this->instance($organizationId, 'https://gitlab-two.example.test');
        $this->instances()->save($instance);
        $this->instances()->save($sourceProviderInstance);

        $authorization         = $this->authorization($organizationId, $instance->getIdentifier()->toString());
        $providerAuthorization = $this->authorization($organizationId, $sourceProviderInstance->getIdentifier()->toString());
        $this->authorizations()->save($authorization);
        $this->authorizations()->save($providerAuthorization);

        $sourceOAuthAccount      = $this->oauthAccount($instance->getIdentifier()->toString());
        $otherAccount            = $this->oauthAccount($sourceProviderInstance->getIdentifier()->toString());
        $this->oauthAccounts()->save($sourceOAuthAccount);
        $this->oauthAccounts()->save($otherAccount);

        $sourceProviderInstanceCleanupResult = $this->cleanup()->deleteWithInactiveAuthorizations(
            $organizationId,
            $instance->getIdentifier()->toString(),
        );

        self::assertSame(1, $sourceProviderInstanceCleanupResult->deletedInactiveAuthorizationsCount);
        self::assertSame(1, $sourceProviderInstanceCleanupResult->deletedSourceOAuthAccountsCount);
        self::assertNull($this->instances()->findByIdInOrganization($instance->getIdentifier()->toString(), $organizationId));
        self::assertSame($sourceProviderInstance->getIdentifier()->toString(), $this->instances()->findByIdInOrganization($sourceProviderInstance->getIdentifier()->toString(), $organizationId)?->getIdentifier()->toString());
        self::assertSame([], $this->authorizations()->findByProviderInstance($instance->getIdentifier()->toString(), $organizationId));
        self::assertCount(1, $this->authorizations()->findByProviderInstance($sourceProviderInstance->getIdentifier()->toString(), $organizationId));
        self::assertSame([], $this->oauthAccounts()->findByProviderInstance($instance->getIdentifier()->toString()));
        self::assertCount(1, $this->oauthAccounts()->findByProviderInstance($sourceProviderInstance->getIdentifier()->toString()));
    }

    #[Test]
    public function rollsBackWhenASourceBlocksCleanup(): void
    {
        $organizationId = Uuid::uuid7()->toString();
        $instance       = $this->instance($organizationId, 'https://gitlab-blocked.example.test');
        $this->instances()->save($instance);

        $instanceId               = $instance->getIdentifier()->toString();
        $authorization            = $this->authorization($organizationId, $instanceId);
        $sourceOAuthAccount       = $this->oauthAccount($instanceId);
        $source                   = Source::register(
            $organizationId,
            'gitlab_self_hosted',
            'https://gitlab-blocked.example.test/group/project',
            'Blocked source',
            'main',
        );
        $source->assignProviderInstance($instanceId);
        $source->setIdentifier(Uuid::uuid7());
        $this->authorizations()->save($authorization);
        $this->oauthAccounts()->save($sourceOAuthAccount);
        $this->sources()->save($source);

        $this->expectException(SourceProviderInstanceException::class);
        $this->expectExceptionMessage('cannot be deleted');

        try {
            $this->cleanup()->deleteWithInactiveAuthorizations($organizationId, $instanceId);
        } finally {
            self::assertSame($instanceId, $this->instances()->findByIdInOrganization($instanceId, $organizationId)?->getIdentifier()->toString());
            self::assertCount(1, $this->authorizations()->findByProviderInstance($instanceId, $organizationId));
            self::assertCount(1, $this->oauthAccounts()->findByProviderInstance($instanceId));
            self::assertCount(1, $this->sources()->findByProviderInstance($instanceId, $organizationId));
        }
    }

    #[Test]
    public function rollsBackWhenAnAuthorizationBecomesActive(): void
    {
        $organizationId = Uuid::uuid7()->toString();
        $instance       = $this->instance($organizationId, 'https://gitlab-active.example.test');
        $this->instances()->save($instance);

        $instanceId    = $instance->getIdentifier()->toString();
        $authorization = $this->authorization($organizationId, $instanceId);
        $authorization->updateStatus('active');
        $sourceOAuthAccount = $this->oauthAccount($instanceId);
        $this->authorizations()->save($authorization);
        $this->oauthAccounts()->save($sourceOAuthAccount);

        $this->expectException(SourceProviderInstanceException::class);
        $this->expectExceptionMessage('cannot be deleted');

        try {
            $this->cleanup()->deleteWithInactiveAuthorizations($organizationId, $instanceId);
        } finally {
            self::assertSame($instanceId, $this->instances()->findByIdInOrganization($instanceId, $organizationId)?->getIdentifier()->toString());
            self::assertSame('active', $this->authorizations()->findByProviderInstance($instanceId, $organizationId)[0]->status());
            self::assertCount(1, $this->oauthAccounts()->findByProviderInstance($instanceId));
        }
    }

    #[Test]
    public function rollsBackWhenASourceReferencesAnInstancesAuthorizationDirectly(): void
    {
        $organizationId = Uuid::uuid7()->toString();
        $instance       = $this->instance($organizationId, 'https://gitlab-direct-auth-source.example.test');
        $this->instances()->save($instance);

        $instanceId    = $instance->getIdentifier()->toString();
        $authorization = $this->authorization($organizationId, $instanceId);
        $this->authorizations()->save($authorization);
        $source = Source::register(
            $organizationId,
            'gitlab_self_hosted',
            'https://gitlab-direct-auth-source.example.test/group/project',
            'Direct authorization source',
            'main',
            'public',
            $authorization->getIdentifier()->toString(),
        );
        $source->setIdentifier(Uuid::uuid7());
        $this->sources()->save($source);

        $this->expectException(SourceProviderInstanceException::class);
        $this->expectExceptionMessage('cannot be deleted');

        try {
            $this->cleanup()->deleteWithInactiveAuthorizations($organizationId, $instanceId);
        } finally {
            self::assertSame($instanceId, $this->instances()->findByIdInOrganization($instanceId, $organizationId)?->getIdentifier()->toString());
            self::assertCount(1, $this->authorizations()->findByProviderInstance($instanceId, $organizationId));
            self::assertCount(1, $this->sources()->findByProviderAuthorizationId($authorization->getIdentifier()->toString(), $organizationId));
        }
    }

    #[Test]
    public function dependencySummaryDeduplicatesASourceLinkedThroughBothInstanceAndAuthorization(): void
    {
        $organizationId = Uuid::uuid7()->toString();
        $instance       = $this->instance($organizationId, 'https://gitlab-duplicate-dependency.example.test');
        $this->instances()->save($instance);
        $instanceId    = $instance->getIdentifier()->toString();
        $authorization = $this->authorization($organizationId, $instanceId);
        $this->authorizations()->save($authorization);

        $source = Source::register(
            $organizationId,
            'gitlab_self_hosted',
            'https://gitlab-duplicate-dependency.example.test/group/project',
            'Dual-linked source',
            'main',
            'public',
            $authorization->getIdentifier()->toString(),
        );
        $source->assignProviderInstance($instanceId);
        $source->setIdentifier(Uuid::uuid7());
        $this->sources()->save($source);

        self::assertCount(1, $this->service()->dependencies($organizationId, $instanceId)->sources);
    }

    #[Test]
    public function instanceScopedOAuthPersistenceRejectsAnInstanceDeletedAfterTheOAuthExchange(): void
    {
        $organizationId = Uuid::uuid7()->toString();
        $instance       = $this->instance($organizationId, 'https://gitlab-oauth-revalidation.example.test');
        $this->instances()->save($instance);
        $instanceId = $instance->getIdentifier()->toString();

        $this->cleanup()->deleteWithInactiveAuthorizations($organizationId, $instanceId);

        $this->expectException(SourceProviderInstanceUnavailableException::class);

        try {
            $this->oauthAccounts()->saveOrUpdateByCredentialKey(
                $this->oauthAccount($instanceId),
                static function(SourceOAuthAccount $sourceOAuthAccount): void {},
                $organizationId,
            );
        } finally {
            self::assertSame([], $this->oauthAccounts()->findByProviderInstance($instanceId));
        }
    }

    #[Test]
    public function instanceScopedLockDoesNotBlockWritesForAnotherInstanceInTheSameOrganization(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $organizationId              = Uuid::uuid7()->toString();
        $sourceProviderInstance      = $this->instance($organizationId, 'https://gitlab-lock-a.example.test');
        $instanceB                   = $this->instance($organizationId, 'https://gitlab-lock-b.example.test');
        $this->instances()->save($sourceProviderInstance);
        $this->instances()->save($instanceB);
        $instanceAId = $sourceProviderInstance->getIdentifier()->toString();
        $instanceBId = $instanceB->getIdentifier()->toString();

        $this->disconnectDatabaseBeforeFork();
        $errorPath = tempnam(sys_get_temp_dir(), 'packio-source-instance-lock-');
        self::assertIsString($errorPath);
        $lockedSockets  = $this->socketPair();
        $startSockets   = $this->socketPair();
        $savedSockets   = $this->socketPair();
        $releaseSockets = $this->socketPair();
        $writerPid      = $this->forkDependencyWriter($organizationId, $instanceAId, '', 'source', $lockedSockets[1], $startSockets[1], $savedSockets[1], $releaseSockets[1], $errorPath);
        fclose($lockedSockets[1]);
        fclose($startSockets[1]);
        fclose($savedSockets[1]);
        fclose($releaseSockets[1]);
        self::assertSame('1', fread($lockedSockets[0], 1));
        fclose($lockedSockets[0]);

        $this->authorizations()->save($this->authorization($organizationId, $instanceBId));

        fwrite($startSockets[0], '1');
        fclose($startSockets[0]);
        self::assertSame('1', fread($savedSockets[0], 1));
        fclose($savedSockets[0]);
        fwrite($releaseSockets[0], '1');
        fclose($releaseSockets[0]);
        $this->assertChildExitedSuccessfully($writerPid, $errorPath);
        self::assertCount(1, $this->authorizations()->findByProviderInstance($instanceBId, $organizationId));
        @unlink($errorPath);
    }

    #[Test]
    #[DataProvider('concurrentDependencyWriterKinds')]
    public function concurrentDependencyWritersCannotEvadeLifecycleDeletion(
        string $dependencyType,
        string $expectedCleanupOutcome,
        bool $strictDelete,
    ): void {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('This test requires the pcntl extension.');
        }

        $organizationId = Uuid::uuid7()->toString();
        $instance       = $this->instance($organizationId, 'https://gitlab-concurrency.example.test');
        $this->instances()->save($instance);
        $instanceId      = $instance->getIdentifier()->toString();
        $authorizationId = '';
        if (! $strictDelete) {
            $authorization   = $this->authorization($organizationId, $instanceId);
            $authorizationId = $authorization->getIdentifier()->toString();
            $this->authorizations()->save($authorization);
        }

        $this->disconnectDatabaseBeforeFork();
        $errorPath = tempnam(sys_get_temp_dir(), 'packio-source-cleanup-concurrency-');
        self::assertIsString($errorPath);

        $lockedSockets  = $this->socketPair();
        $startSockets   = $this->socketPair();
        $savedSockets   = $this->socketPair();
        $releaseSockets = $this->socketPair();
        $resultSockets  = $this->socketPair();

        $writerPid = $this->forkDependencyWriter(
            $organizationId,
            $instanceId,
            $authorizationId,
            $dependencyType,
            $lockedSockets[1],
            $startSockets[1],
            $savedSockets[1],
            $releaseSockets[1],
            $errorPath,
        );
        fclose($lockedSockets[1]);
        fclose($startSockets[1]);
        fclose($savedSockets[1]);
        fclose($releaseSockets[1]);

        self::assertSame('1', fread($lockedSockets[0], 1));
        fclose($lockedSockets[0]);

        $cleanupPid = $this->forkCleanup($organizationId, $instanceId, $strictDelete, $resultSockets[1], $errorPath);
        fclose($resultSockets[1]);
        $read   = [$resultSockets[0]];
        $write  = null;
        $except = null;
        self::assertSame(0, stream_select($read, $write, $except, 0, 300_000), 'Cleanup completed while the dependency writer held the shared transaction lock.');

        fwrite($startSockets[0], '1');
        fclose($startSockets[0]);
        self::assertSame('1', fread($savedSockets[0], 1));
        fclose($savedSockets[0]);

        fwrite($releaseSockets[0], '1');
        fclose($releaseSockets[0]);
        self::assertSame($expectedCleanupOutcome, fread($resultSockets[0], 7));
        fclose($resultSockets[0]);

        $this->assertChildExitedSuccessfully($writerPid, $errorPath);
        $this->assertChildExitedSuccessfully($cleanupPid, $errorPath);
        if ('source' === $dependencyType) {
            self::assertSame($instanceId, $this->instances()->findByIdInOrganization($instanceId, $organizationId)?->getIdentifier()->toString());
            self::assertCount(1, $this->sources()->findByProviderInstance($instanceId, $organizationId));
        } elseif ('authorization' === $dependencyType) {
            self::assertSame($instanceId, $this->instances()->findByIdInOrganization($instanceId, $organizationId)?->getIdentifier()->toString());
            $authorizations = $this->authorizations()->findByProviderInstance($instanceId, $organizationId);
            self::assertCount(2, $authorizations);
            self::assertContains('active', array_map(static fn (ProviderAuthorization $providerAuthorization): string => $providerAuthorization->status(), $authorizations));
        } else {
            self::assertNull($this->instances()->findByIdInOrganization($instanceId, $organizationId));
            self::assertSame([], $this->oauthAccounts()->findByProviderInstance($instanceId));
        }

        @unlink($errorPath);
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function concurrentDependencyWriterKinds(): iterable
    {
        yield 'source writer' => ['source', 'blocked', false];

        yield 'authorization writer' => ['authorization', 'blocked', false];

        yield 'OAuth account writer' => ['oauth_account', 'deleted', false];

        yield 'strict delete and source writer' => ['source', 'blocked', true];
    }

    /**
     * @return array{0: resource, 1: resource}
     */
    private function socketPair(): array
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($sockets);

        return [$sockets[0], $sockets[1]];
    }

    /**
     * @param resource $lockedSocket
     * @param resource $startSocket
     * @param resource $savedSocket
     * @param resource $releaseSocket
     */
    private function forkDependencyWriter(
        string $organizationId,
        string $instanceId,
        string $authorizationId,
        string $dependencyType,
        mixed $lockedSocket,
        mixed $startSocket,
        mixed $savedSocket,
        mixed $releaseSocket,
        string $errorPath,
    ): int {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);

        if (0 === $pid) {
            try {
                $this->instances()->transactionally(function(DatabaseInterface $database) use ($organizationId, $instanceId, $authorizationId, $dependencyType, $lockedSocket, $startSocket, $savedSocket, $releaseSocket): void {
                    $database->query('SELECT pg_advisory_xact_lock(hashtext(?))', ['source-provider-instance-dependencies:' . $organizationId . ':' . $instanceId]);
                    fwrite($lockedSocket, '1');
                    fclose($lockedSocket);
                    @fread($startSocket, 1);
                    fclose($startSocket);

                    if ('source' === $dependencyType) {
                        $source = Source::register(
                            $organizationId,
                            'gitlab_self_hosted',
                            'https://gitlab-concurrency.example.test/group/project',
                            'Concurrent source',
                            'main',
                            'public',
                            $authorizationId,
                        );
                        $source->assignProviderInstance($instanceId);
                        $source->setIdentifier(Uuid::uuid7());
                        $this->sources()->save($source);
                    } elseif ('authorization' === $dependencyType) {
                        $authorization = $this->authorization($organizationId, $instanceId);
                        $authorization->updateStatus('active');
                        $this->authorizations()->save($authorization);
                    } else {
                        $this->oauthAccounts()->saveOrUpdateByCredentialKey(
                            $this->oauthAccount($instanceId),
                            static function(SourceOAuthAccount $sourceOAuthAccount): void {},
                            $organizationId,
                        );
                    }
                    fwrite($savedSocket, '1');
                    fclose($savedSocket);
                    @fread($releaseSocket, 1);
                    fclose($releaseSocket);
                });

                exit(0);
            } catch (Throwable $throwable) {
                $this->recordChildError($errorPath, $throwable);

                exit(1);
            }
        }

        return $pid;
    }

    /** @param resource $resultSocket */
    private function forkCleanup(
        string $organizationId,
        string $instanceId,
        bool $strictDelete,
        mixed $resultSocket,
        string $errorPath
    ): int {
        $pid = pcntl_fork();
        self::assertNotFalse($pid);

        if (0 === $pid) {
            try {
                if ($strictDelete) {
                    $this->cleanup()->deleteStrict($organizationId, $instanceId);
                } else {
                    $this->cleanup()->deleteWithInactiveAuthorizations($organizationId, $instanceId);
                }
                fwrite($resultSocket, 'deleted');
                fclose($resultSocket);

                exit(0);
            } catch (SourceProviderInstanceException) {
                fwrite($resultSocket, 'blocked');
                fclose($resultSocket);

                exit(0);
            } catch (Throwable $throwable) {
                $this->recordChildError($errorPath, $throwable);

                exit(1);
            }
        }

        return $pid;
    }

    private function recordChildError(string $errorPath, Throwable $throwable): void
    {
        file_put_contents(
            $errorPath,
            sprintf('%s: %s\n', $throwable::class, $throwable->getMessage()),
            FILE_APPEND,
        );
    }

    private function instance(string $organizationId, string $baseUrl): SourceProviderInstance
    {
        $sourceProviderInstance = SourceProviderInstance::create(
            organizationId: $organizationId,
            providerType: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            label: 'Company GitLab',
            baseUrl: $baseUrl,
            clientId: 'client-id',
            clientSecret: 'encrypted-client-secret',
            authMode: SourceProviderInstance::AUTH_MODE_OAUTH2_AND_TOKEN,
        );
        $sourceProviderInstance->setIdentifier(Uuid::uuid7());

        return $sourceProviderInstance;
    }

    private function authorization(string $organizationId, string $instanceId): ProviderAuthorization
    {
        $providerAuthorization = ProviderAuthorization::authorize(
            $organizationId,
            SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            'oauth',
            [],
            'Former administrator',
        );
        $providerAuthorization->assignProviderInstance($instanceId);
        $providerAuthorization->updateStatus('inactive');
        $providerAuthorization->setIdentifier(Uuid::uuid7());

        return $providerAuthorization;
    }

    private function oauthAccount(string $instanceId): SourceOAuthAccount
    {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: Uuid::uuid7()->toString(),
            provider: SourceProviderInstance::PROVIDER_GITLAB_SELF_HOSTED,
            providerUserId: Uuid::uuid7()->toString(),
            accessToken: 'encrypted-access-token',
            providerInstanceId: $instanceId,
            providerBaseUrl: 'https://gitlab.example.test',
        );
        $sourceOAuthAccount->setIdentifier(Uuid::uuid7());

        return $sourceOAuthAccount;
    }

    private function instances(): SourceProviderInstanceRepository
    {
        $repository = $this->getService(SourceProviderInstanceRepository::class);
        self::assertInstanceOf(SourceProviderInstanceRepository::class, $repository);

        return $repository;
    }

    private function authorizations(): ProviderAuthorizationRepository
    {
        $repository = $this->getService(ProviderAuthorizationRepository::class);
        self::assertInstanceOf(ProviderAuthorizationRepository::class, $repository);

        return $repository;
    }

    private function oauthAccounts(): SourceOAuthAccountRepository
    {
        $repository = $this->getService(SourceOAuthAccountRepository::class);
        self::assertInstanceOf(SourceOAuthAccountRepository::class, $repository);

        return $repository;
    }

    private function sources(): SourceRepository
    {
        $repository = $this->getService(SourceRepository::class);
        self::assertInstanceOf(SourceRepository::class, $repository);

        return $repository;
    }

    private function cleanup(): SourceProviderInstanceCleanupRepository
    {
        $repository = $this->getService(SourceProviderInstanceCleanupRepository::class);
        self::assertInstanceOf(SourceProviderInstanceCleanupRepository::class, $repository);

        return $repository;
    }

    private function service(): SourceProviderInstanceService
    {
        $service = $this->getService(SourceProviderInstanceService::class);
        self::assertInstanceOf(SourceProviderInstanceService::class, $service);

        return $service;
    }
}
