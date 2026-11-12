<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Infrastructure\Cli;

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
use Packio\Source\Application\Service\SourceOAuthService;
use Packio\Source\Application\Service\SourceOAuthTokenResolver;
use Packio\Source\Domain\Dto\SourceOAuthRefreshCriteria;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Source\Infrastructure\Adapter\WorkerJobSchedulerAdapter;
use Packio\Source\Infrastructure\Cli\SourceOAuthRefreshMaintenanceCommand;
use Packio\Worker\Api\Dto\JobFilterCriteria;
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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Sirix\Cycle\Extension\Domain\Contract\EntityInterface;
use Sirix\ObjectMapper\Contract\ObjectMapperInterface;
use Symfony\Component\Console\Tester\CommandTester;

use function array_filter;
use function array_values;
use function str_starts_with;
use function substr;

#[CoversClass(SourceOAuthRefreshMaintenanceCommand::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceOAuthRefreshMaintenanceCommandTest extends TestCase
{
    use InertSourceCollaborators;

    #[Test]
    public function dryRunReportsCandidatesWithoutEnqueueingJobs(): void
    {
        $account                                          = $this->account();
        $authorization                                    = $this->authorization($account);
        $sourceOAuthRefreshCliAccountRepositoryStub       = new SourceOAuthRefreshCliAccountRepositoryStub([$account]);
        $sourceOAuthRefreshCliAuthorizationRepositoryStub = new SourceOAuthRefreshCliAuthorizationRepositoryStub([$authorization]);
        $sourceOAuthRefreshCliJobRepositoryStub           = new SourceOAuthRefreshCliJobRepositoryStub();
        $sourceOAuthRefreshCliSecretEncryptorStub         = new SourceOAuthRefreshCliSecretEncryptorStub();
        $sourceOAuthRefreshCliOAuthProviderStub           = new SourceOAuthRefreshCliOAuthProviderStub();
        $sourceRepo                                       = $this->createMock(SourceRepositoryInterface::class);
        $orgPort                                          = $this->createMock(OrganizationQueryPort::class);
        $orgPort->method('resolveOrganizationId')->with('acme')->willReturn('org-1');

        $sourceOAuthService                   = new SourceOAuthService($sourceOAuthRefreshCliOAuthProviderStub, $sourceOAuthRefreshCliAccountRepositoryStub, new SourceOAuthAccountSecrets($sourceOAuthRefreshCliSecretEncryptorStub), $orgPort, $this->createStub(SourceProviderInstanceRepositoryInterface::class), $this->inertSourceProviderInstanceOAuthConfig());
        $sourceOAuthRefreshMaintenanceService = new SourceOAuthRefreshMaintenanceService(
            $sourceOAuthRefreshCliAccountRepositoryStub,
            $sourceOAuthRefreshCliAuthorizationRepositoryStub,
            new SourceOAuthTokenResolver($sourceOAuthRefreshCliAccountRepositoryStub, $sourceOAuthRefreshCliAuthorizationRepositoryStub, $sourceRepo, new SourceOAuthAccountSecrets($sourceOAuthRefreshCliSecretEncryptorStub), $sourceOAuthRefreshCliOAuthProviderStub, $this->createStub(SourceProviderInstanceRepositoryInterface::class), $this->inertSourceProviderInstanceOAuthConfig()),
            new SourceOAuthRefreshPolicy(),
            $this->jobApiFacade($sourceOAuthRefreshCliJobRepositoryStub),
            new SourceAuthorizationInvalidationService($sourceRepo, $sourceOAuthRefreshCliAuthorizationRepositoryStub, $sourceOAuthService),
        );

        $commandTester = new CommandTester(new SourceOAuthRefreshMaintenanceCommand(
            new SourceOAuthRefreshPolicy(),
            $sourceOAuthRefreshMaintenanceService,
            $orgPort,
        ));

        $exitCode = $commandTester->execute([
            '--organization' => 'acme',
            '--dry-run'      => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('candidates=1 dry_run=1 orphaned=0', $commandTester->getDisplay());
        self::assertSame([], $sourceOAuthRefreshCliJobRepositoryStub->savedJobs);
    }

    private function account(): SourceOAuthAccount
    {
        $sourceOAuthAccount = new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: 'provider-user-1',
            accessToken: 'enc:access-token',
            refreshToken: 'enc:refresh-token',
            tokenExpiresAt: Chronos::parse('+1 hour'),
        );
        $sourceOAuthAccount->setIdentifier(Uuid::uuid7());

        return $sourceOAuthAccount;
    }

    private function authorization(SourceOAuthAccount $sourceOAuthAccount): ProviderAuthorization
    {
        $providerAuthorization = ProviderAuthorization::authorizeFromOAuth(
            organizationId: 'org-1',
            provider: 'github',
            sourceOAuthAccountId: $sourceOAuthAccount->getIdentifier()->toString(),
            authStrategy: 'oauth2',
            configuration: [],
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

final class SourceOAuthRefreshCliAccountRepositoryStub implements SourceOAuthAccountRepositoryInterface
{
    /** @var array<string, SourceOAuthAccount> */
    private array $accounts = [];

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
        return array_values($this->accounts);
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

final class SourceOAuthRefreshCliAuthorizationRepositoryStub implements ProviderAuthorizationRepositoryInterface
{
    /** @var array<string, ProviderAuthorization> */
    private array $authorizations = [];

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
        return null;
    }

    public function findByOrganization(string $organizationId, ?string $provider = null): array
    {
        return [];
    }

    public function findActiveBySourceOAuthAccountId(string $sourceOAuthAccountId, ?string $organizationId = null): array
    {
        return array_values(array_filter(
            $this->authorizations,
            static fn (ProviderAuthorization $providerAuthorization): bool => $providerAuthorization->sourceOAuthAccountId() === $sourceOAuthAccountId
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

final class SourceOAuthRefreshCliJobRepositoryStub implements JobRepositoryInterface
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

final class SourceOAuthRefreshCliSecretEncryptorStub implements SecretEncryptorInterface
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

final class SourceOAuthRefreshCliOAuthProviderStub implements OAuthProviderPort
{
    public function exchangeCodeForToken(string $provider, string $code, ?string $codeVerifier = null): OAuthTokenData
    {
        return new OAuthTokenData('access');
    }

    public function exchangeCodeForTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $code,
        ?string $codeVerifier = null
    ): OAuthTokenData {
        return new OAuthTokenData('access');
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
        return new OAuthTokenData('access', 'refresh', 7200);
    }

    public function refreshAccessTokenForProviderInstance(
        string $provider,
        array $providerInstanceConfig,
        string $refreshToken
    ): OAuthTokenData {
        return new OAuthTokenData('access', 'refresh', 7200);
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
