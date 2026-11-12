<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Cake\Chronos\Chronos;
use Common\Security\SecretEncryptorInterface;
use LogicException;
use Packio\Shared\Domain\Pagination\PaginationCriteria;
use Packio\Source\Application\Dto\OAuthTokenData;
use Packio\Source\Application\Dto\OAuthUserData;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Service\SourceAuthorizationInvalidationService;
use Packio\Source\Application\Service\SourceOAuthRefreshMaintenanceService;
use Packio\Source\Application\Service\SourceOAuthRefreshPolicy;
use Packio\Source\Application\Service\SourceOAuthRefreshReason;
use Packio\Source\Application\Service\SourceOAuthService;
use Packio\Source\Application\Service\SourceOAuthTokenResolver;
use Packio\Source\Domain\Dto\SourceOAuthRefreshCriteria;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Exception\ProviderRefreshRejectedException;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Source\Infrastructure\Adapter\Worker\SourceOAuthRefreshJobHandler;
use Packio\Source\Infrastructure\Adapter\WorkerJobSchedulerAdapter;
use Packio\Worker\Api\Dto\JobFilterCriteria;
use Packio\Worker\Api\Job\JobLoggerInterface;
use Packio\Worker\Application\JobQueueService;
use Packio\Worker\Application\JobRestartService;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use Packio\Worker\Infrastructure\Facade\JobApiFacade;
use Packio\Worker\Infrastructure\Mapper\JobDtoMapper;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;

use function array_filter;
use function array_values;
use function str_starts_with;
use function substr;

#[CoversClass(SourceOAuthRefreshMaintenanceService::class)]
#[CoversClass(SourceOAuthRefreshJobHandler::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceOAuthRefreshMaintenanceServiceTest extends TestCase
{
    use InertSourceCollaborators;

    #[Test]
    public function scheduleEnqueuesOneRefreshJobForLinkedCandidate(): void
    {
        $account       = $this->account(tokenExpiresAt: Chronos::parse('+1 hour'));
        $authorization = $this->authorization($account);
        $dependencies  = $this->dependencies([$account], [$authorization]);

        $sourceOAuthRefreshScheduleResult = $dependencies->service->schedule($this->criteria(), dryRun: false);

        self::assertSame(1, $sourceOAuthRefreshScheduleResult->candidateCount());
        self::assertSame(1, $sourceOAuthRefreshScheduleResult->scheduledCount());
        self::assertSame(0, $sourceOAuthRefreshScheduleResult->orphanedCount());
        self::assertCount(1, $dependencies->jobRepository->savedJobs);
        self::assertSame(SourceOAuthRefreshJobHandler::TYPE, $dependencies->jobRepository->savedJobs[0]->type());
        self::assertSame($account->getIdentifier()->toString(), $dependencies->jobRepository->savedJobs[0]->payload()['source_oauth_account_id']);
    }

    #[Test]
    public function scheduleReportsOrphanedCandidatesWithoutEnqueueing(): void
    {
        $account      = $this->account(tokenExpiresAt: Chronos::parse('+1 hour'));
        $dependencies = $this->dependencies([$account], []);

        $sourceOAuthRefreshScheduleResult = $dependencies->service->schedule($this->criteria(), dryRun: false);

        self::assertSame(1, $sourceOAuthRefreshScheduleResult->candidateCount());
        self::assertSame(0, $sourceOAuthRefreshScheduleResult->scheduledCount());
        self::assertSame(1, $sourceOAuthRefreshScheduleResult->orphanedCount());
        self::assertSame([], $dependencies->jobRepository->savedJobs);
    }

    #[Test]
    public function refreshJobForcesTokenRefreshAndPersistsRotatedCredentials(): void
    {
        $account       = $this->account(tokenExpiresAt: Chronos::parse('+2 hours'));
        $authorization = $this->authorization($account);
        $dependencies  = $this->dependencies([$account], [$authorization]);

        $dependencies->oauthProvider->nextToken = new OAuthTokenData('new-access-token', 'new-refresh-token', 7200);

        $sourceOAuthRefreshJobHandler = new SourceOAuthRefreshJobHandler($dependencies->service);
        $jobResult                    = $sourceOAuthRefreshJobHandler->handle([
            'source_oauth_account_id'   => $account->getIdentifier()->toString(),
            'provider_authorization_id' => $authorization->getIdentifier()->toString(),
            'reason'                    => SourceOAuthRefreshReason::IdleKeepalive->value,
        ], new SourceOAuthRefreshJobLoggerStub());

        self::assertTrue($jobResult->isSuccess());
        self::assertSame(1, $dependencies->oauthProvider->refreshCalls);
        self::assertSame('enc:new-access-token', $account->storedAccessToken());
        self::assertSame('enc:new-refresh-token', $account->storedRefreshToken());
    }

    #[Test]
    public function refreshJobDoesNotRetryPermanentAuthFailures(): void
    {
        $account      = $this->account(tokenExpiresAt: Chronos::parse('-1 hour'), refreshToken: null);
        $dependencies = $this->dependencies([$account], [$this->authorization($account)]);

        $sourceOAuthRefreshJobHandler = new SourceOAuthRefreshJobHandler($dependencies->service);
        $jobResult                    = $sourceOAuthRefreshJobHandler->handle([
            'source_oauth_account_id' => $account->getIdentifier()->toString(),
            'reason'                  => SourceOAuthRefreshReason::ExpiresSoon->value,
        ], new SourceOAuthRefreshJobLoggerStub());

        self::assertFalse($jobResult->isSuccess());
        self::assertFalse($jobResult->shouldRetry());
    }

    #[Test]
    #[DataProvider('refreshFailures')]
    public function refreshJobInvalidatesOnlyRejectedAuthorizationsAndRetriesTransientFailures(
        RuntimeException $runtimeException,
        bool $rejected
    ): void {
        $account       = $this->account(tokenExpiresAt: Chronos::parse('-1 hour'));
        $authorization = $this->authorization($account);
        $dependencies  = $this->dependencies([$account], [$authorization]);

        $dependencies->oauthProvider->nextFailure = $runtimeException;

        $jobResult = new SourceOAuthRefreshJobHandler($dependencies->service)->handle([
            'source_oauth_account_id'   => $account->getIdentifier()->toString(),
            'provider_authorization_id' => $authorization->getIdentifier()->toString(),
            'reason'                    => SourceOAuthRefreshReason::ExpiresSoon->value,
        ], new SourceOAuthRefreshJobLoggerStub());

        self::assertFalse($jobResult->isSuccess());
        self::assertSame(! $rejected, $jobResult->shouldRetry());
        self::assertSame($rejected ? 'inactive' : 'active', $authorization->status());
    }

    /** @return array<string, array{RuntimeException, bool}> */
    public static function refreshFailures(): array
    {
        return [
            'provider rejection' => [new ProviderRefreshRejectedException('invalid_grant'), true],
            'transient failure'  => [new RuntimeException('Failed to refresh token: invalid response'), false],
        ];
    }

    /**
     * @param list<SourceOAuthAccount>    $accounts
     * @param list<ProviderAuthorization> $authorizations
     */
    private function dependencies(array $accounts, array $authorizations): SourceOAuthRefreshDependencies
    {
        $sourceOAuthRefreshAccountRepositoryStub       = new SourceOAuthRefreshAccountRepositoryStub($accounts);
        $sourceOAuthRefreshAuthorizationRepositoryStub = new SourceOAuthRefreshAuthorizationRepositoryStub($authorizations);
        $sourceOAuthRefreshJobRepositoryStub           = new SourceOAuthRefreshJobRepositoryStub();
        $sourceOAuthRefreshSecretEncryptorStub         = new SourceOAuthRefreshSecretEncryptorStub();
        $sourceOAuthRefreshOAuthProviderStub           = new SourceOAuthRefreshOAuthProviderStub();
        $sourceRepository                              = $this->createMock(SourceRepositoryInterface::class);
        $organizationQueryPort                         = $this->createMock(OrganizationQueryPort::class);

        $sourceOAuthService = new SourceOAuthService(
            $sourceOAuthRefreshOAuthProviderStub,
            $sourceOAuthRefreshAccountRepositoryStub,
            new SourceOAuthAccountSecrets($sourceOAuthRefreshSecretEncryptorStub),
            $organizationQueryPort,
            $this->createStub(SourceProviderInstanceRepositoryInterface::class),
            $this->inertSourceProviderInstanceOAuthConfig(),
        );
        $sourceAuthorizationInvalidationService = new SourceAuthorizationInvalidationService(
            $sourceRepository,
            $sourceOAuthRefreshAuthorizationRepositoryStub,
            $sourceOAuthService,
        );
        $sourceOAuthTokenResolver = new SourceOAuthTokenResolver(
            $sourceOAuthRefreshAccountRepositoryStub,
            $sourceOAuthRefreshAuthorizationRepositoryStub,
            $sourceRepository,
            new SourceOAuthAccountSecrets($sourceOAuthRefreshSecretEncryptorStub),
            $sourceOAuthRefreshOAuthProviderStub,
            $this->createStub(SourceProviderInstanceRepositoryInterface::class),
            $this->inertSourceProviderInstanceOAuthConfig(),
        );
        $sourceOAuthRefreshMaintenanceService = new SourceOAuthRefreshMaintenanceService(
            $sourceOAuthRefreshAccountRepositoryStub,
            $sourceOAuthRefreshAuthorizationRepositoryStub,
            $sourceOAuthTokenResolver,
            new SourceOAuthRefreshPolicy(),
            $this->jobApiFacade($sourceOAuthRefreshJobRepositoryStub),
            $sourceAuthorizationInvalidationService,
        );

        return new SourceOAuthRefreshDependencies(
            service: $sourceOAuthRefreshMaintenanceService,
            jobRepository: $sourceOAuthRefreshJobRepositoryStub,
            oauthProvider: $sourceOAuthRefreshOAuthProviderStub,
        );
    }

    private function criteria(): SourceOAuthRefreshCriteria
    {
        return new SourceOAuthRefreshCriteria(
            tokenExpiresBefore: Chronos::parse('+24 hours'),
            lastUsedBefore: Chronos::parse('-30 days'),
            updatedBefore: Chronos::parse('+1 minute'),
        );
    }

    private function account(?Chronos $tokenExpiresAt, ?string $refreshToken = 'enc:refresh-token'): SourceOAuthAccount
    {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: 'provider-user-1',
            accessToken: 'enc:access-token',
            refreshToken: $refreshToken,
            tokenExpiresAt: $tokenExpiresAt,
        );
        $sourceOAuthAccount->setIdentifier(Uuid::uuid7());

        return $sourceOAuthAccount;
    }

    private function authorization(SourceOAuthAccount $sourceOAuthAccount): ProviderAuthorization
    {
        $providerAuthorization = ProviderAuthorization::authorizeFromOAuth(
            organizationId: 'org-1',
            provider: $sourceOAuthAccount->provider(),
            sourceOAuthAccountId: $sourceOAuthAccount->getIdentifier()->toString(),
            authStrategy: 'oauth2',
            configuration: [],
            accountName: 'Account',
        );
        $providerAuthorization->setIdentifier(Uuid::uuid7());

        return $providerAuthorization;
    }

    private function jobApiFacade(JobRepositoryInterface $jobRepository): WorkerJobSchedulerAdapter
    {
        $jobLogRepository = $this->createStub(JobLogRepositoryInterface::class);

        return new WorkerJobSchedulerAdapter(new JobApiFacade(
            $jobRepository,
            $jobLogRepository,
            new JobQueueService($jobRepository),
            new JobRestartService($jobRepository, $jobLogRepository),
            new JobDtoMapper(),
            $this->createStub(ObjectMapperInterface::class),
        ));
    }
}

final readonly class SourceOAuthRefreshDependencies
{
    public function __construct(
        public SourceOAuthRefreshMaintenanceService $service,
        public SourceOAuthRefreshJobRepositoryStub $jobRepository,
        public SourceOAuthRefreshOAuthProviderStub $oauthProvider,
    ) {}
}

final class SourceOAuthRefreshAccountRepositoryStub implements SourceOAuthAccountRepositoryInterface
{
    /** @var array<string, SourceOAuthAccount> */
    public array $accounts = [];

    /** @param list<SourceOAuthAccount> $accounts */
    public function __construct(array $accounts)
    {
        foreach ($accounts as $account) {
            $this->accounts[$account->getIdentifier()->toString()] = $account;
        }
    }

    public function findById(string $id): ?SourceOAuthAccount
    {
        return $this->accounts[$id] ?? null;
    }

    public function findByCredentialIdentity(
        string $userId,
        string $provider,
        string $providerUserId,
        ?string $providerInstanceId
    ): ?SourceOAuthAccount {
        return null;
    }

    public function findActiveByCredentialIdentity(
        string $userId,
        string $provider,
        string $providerUserId,
        ?string $providerInstanceId
    ): ?SourceOAuthAccount {
        return null;
    }

    public function findActiveAccountsByCredentialIdentity(
        string $userId,
        string $provider,
        string $providerUserId,
        ?string $providerInstanceId
    ): array {
        return [];
    }

    public function findByProviderInstance(string $providerInstanceId): array
    {
        return [];
    }

    public function findLockedByProviderInstance(string $providerInstanceId): array
    {
        return $this->findByProviderInstance($providerInstanceId);
    }

    public function findRefreshCandidates(SourceOAuthRefreshCriteria $sourceOAuthRefreshCriteria): array
    {
        return array_values(array_filter(
            $this->accounts,
            static fn (SourceOAuthAccount $sourceOAuthAccount): bool => $sourceOAuthAccount->isActive() && null !== $sourceOAuthAccount->storedRefreshToken(),
        ));
    }

    public function save(SourceOAuthAccount $sourceOAuthAccount): void
    {
        $this->accounts[$sourceOAuthAccount->getIdentifier()->toString()] = $sourceOAuthAccount;
    }

    public function saveOrUpdateByCredentialKey(
        SourceOAuthAccount $sourceOAuthAccount,
        callable $updateExisting,
        ?string $organizationId = null
    ): SourceOAuthAccount {
        $this->save($sourceOAuthAccount);

        return $sourceOAuthAccount;
    }

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void
    {
        if ($entity instanceof SourceOAuthAccount) {
            unset($this->accounts[$entity->getIdentifier()->toString()]);
        }
    }
}

final class SourceOAuthRefreshAuthorizationRepositoryStub implements ProviderAuthorizationRepositoryInterface
{
    /** @var array<string, ProviderAuthorization> */
    public array $authorizations = [];

    /** @param list<ProviderAuthorization> $authorizations */
    public function __construct(array $authorizations)
    {
        foreach ($authorizations as $authorization) {
            $this->authorizations[$authorization->getIdentifier()->toString()] = $authorization;
        }
    }

    public function save(ProviderAuthorization $providerAuthorization): ProviderAuthorization
    {
        $this->authorizations[$providerAuthorization->getIdentifier()->toString()] = $providerAuthorization;

        return $providerAuthorization;
    }

    public function findById(string $id): ?ProviderAuthorization
    {
        return $this->authorizations[$id] ?? null;
    }

    public function findByIdInOrganization(string $id, string $organizationId): ?ProviderAuthorization
    {
        $authorization = $this->findById($id);

        return $authorization instanceof ProviderAuthorization && $authorization->organizationId() === $organizationId
            ? $authorization
            : null;
    }

    public function findByOrganization(string $organizationId, ?string $provider = null): array
    {
        return [];
    }

    public function findActiveBySourceOAuthAccountId(string $sourceOAuthAccountId, ?string $organizationId = null): array
    {
        return array_values(array_filter(
            $this->authorizations,
            static fn (ProviderAuthorization $providerAuthorization): bool => 'active' === $providerAuthorization->status()
                && $providerAuthorization->sourceOAuthAccountId() === $sourceOAuthAccountId
                && (null === $organizationId || $providerAuthorization->organizationId() === $organizationId),
        ));
    }

    public function findByProviderInstance(string $providerInstanceId, string $organizationId): array
    {
        return [];
    }

    public function findLockedByProviderInstance(string $providerInstanceId, string $organizationId): array
    {
        return $this->findByProviderInstance($providerInstanceId, $organizationId);
    }

    public function deleteAndFlush(EntityInterface $entity, bool $cascade = true): void
    {
        if ($entity instanceof ProviderAuthorization) {
            unset($this->authorizations[$entity->getIdentifier()->toString()]);
        }
    }
}

final class SourceOAuthRefreshJobRepositoryStub implements JobRepositoryInterface
{
    /** @var list<Job> */
    public array $savedJobs = [];

    public function save(Job $job): void
    {
        // The ORM assigns these on persist.
        $job->setIdentifier($job->next());
        $job->setCreatedAt(Chronos::now());
        $this->savedJobs[] = $job;
    }

    public function findById(string $id): ?Job
    {
        return null;
    }

    public function releaseTerminalIdempotencyKey(string $organizationId, string $type, string $idempotencyKey): void
    {
        throw new LogicException('Terminal key release is not supported by this test repository.');
    }

    public function findByIdempotencyKey(string $organizationId, string $type, string $idempotencyKey): ?Job
    {
        return null;
    }

    public function advanceLifecycle(string $id, string $organizationId, string $fromStatus, string $toStatus): bool
    {
        return false;
    }

    public function findSourceDeleteJobs(string $organizationId, string $sourceId): array
    {
        return [];
    }

    public function deleteOlderThan(Chronos $threshold): int
    {
        return 0;
    }

    public function findByOrganization(string $organizationId, array $filters = []): array
    {
        return [];
    }

    public function dequeue(string $workerId, int $timeoutSeconds = 30): ?Job
    {
        return null;
    }

    public function findStuck(int $timeoutMinutes = 30): array
    {
        return [];
    }

    public function findPaginatedAll(PaginationCriteria $paginationCriteria, JobFilterCriteria $jobFilterCriteria): array
    {
        return [
            'items'       => [],
            'total'       => 0,
            'currentPage' => $paginationCriteria->page,
            'perPage'     => $paginationCriteria->perPage,
        ];
    }

    public function distinctTypes(?string $organizationId = null): array
    {
        return [];
    }

    public function countAllByStatus(JobFilterCriteria $jobFilterCriteria): array
    {
        return [
            'pending'   => 0,
            'running'   => 0,
            'completed' => 0,
            'failed'    => 0,
            'canceled'  => 0,
        ];
    }

    public function queueProgress(?string $organizationId = null): array
    {
        unset($organizationId);

        return [
            'pending'                  => 0,
            'running'                  => 0,
            'failed'                   => 0,
            'retrying'                 => 0,
            'stuck'                    => 0,
            'oldest_ready_age_seconds' => null,
        ];
    }
}

final class SourceOAuthRefreshSecretEncryptorStub implements SecretEncryptorInterface
{
    public function encrypt(string $plaintext): string
    {
        return 'enc:' . $plaintext;
    }

    public function decrypt(string $ciphertext): ?string
    {
        return str_starts_with($ciphertext, 'enc:') ? substr($ciphertext, 4) : null;
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, 'enc:');
    }
}

final class SourceOAuthRefreshOAuthProviderStub implements OAuthProviderPort
{
    public OAuthTokenData $nextToken;

    public int $refreshCalls = 0;

    public ?RuntimeException $nextFailure = null;

    public function __construct()
    {
        $this->nextToken = new OAuthTokenData('refreshed-access-token', 'refreshed-refresh-token', 7200);
    }

    public function exchangeCodeForToken(string $provider, string $code, ?string $codeVerifier = null): OAuthTokenData
    {
        return $this->nextToken;
    }

    public function exchangeCodeForTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $code,
        ?string $codeVerifier = null
    ): OAuthTokenData {
        return $this->nextToken;
    }

    public function fetchUser(string $provider, string $accessToken): OAuthUserData
    {
        return new OAuthUserData('provider-user-1');
    }

    public function fetchUserForProviderInstance(string $provider, array $providerInstanceConfig, string $accessToken): OAuthUserData
    {
        return new OAuthUserData('provider-user-1');
    }

    public function sourceScopes(string $provider): array
    {
        return [];
    }

    public function verifyToken(string $provider, string $accessToken): bool
    {
        return true;
    }

    public function refreshAccessToken(string $provider, string $refreshToken): OAuthTokenData
    {
        ++$this->refreshCalls;
        if ($this->nextFailure instanceof RuntimeException) {
            throw $this->nextFailure;
        }

        return $this->nextToken;
    }

    public function refreshAccessTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $refreshToken
    ): OAuthTokenData {
        ++$this->refreshCalls;

        return $this->nextToken;
    }

    public function getAuthorizationUrlForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $state,
        array $scopes,
        ?string $codeChallenge = null
    ): string {
        return 'https://example.test/oauth';
    }

    public function sourceConnectAvailability(): array
    {
        return [];
    }
}

final class SourceOAuthRefreshJobLoggerStub implements JobLoggerInterface
{
    public function debug(string $message, array $context = []): void {}

    public function info(string $message, array $context = []): void {}

    public function warning(string $message, array $context = []): void {}

    public function error(string $message, array $context = []): void {}
}
