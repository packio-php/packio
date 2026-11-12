<?php

declare(strict_types=1);

namespace PackioTest\Unit\Source\Application\Service;

use Common\Logging\SafeExceptionContext;
use Common\Security\SecretEncryptorInterface;
use Common\Security\SodiumSecretEncryptor;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Command\SourceMaintenanceService;
use Packio\Source\Application\Dto\SourceSyncResult;
use Packio\Source\Application\Port\JobSchedulerPort;
use Packio\Source\Application\Port\OAuthProviderPort;
use Packio\Source\Application\Port\OrganizationQueryPort;
use Packio\Source\Application\Port\PublicationSyncPort;
use Packio\Source\Application\Provider\SourceProviderRegistryInterface;
use Packio\Source\Application\Security\ProviderAuthorizationFactory;
use Packio\Source\Application\Security\ProviderAuthorizationSecrets;
use Packio\Source\Application\Security\SourceOAuthAccountSecrets;
use Packio\Source\Application\Security\SourceWebhookSecrets;
use Packio\Source\Application\Service\SourceAuthorizationInvalidationService;
use Packio\Source\Application\Service\SourceOAuthAccountOwnershipVerifierInterface;
use Packio\Source\Application\Service\SourceOAuthService;
use Packio\Source\Application\Service\SourceSyncExecutionService;
use Packio\Source\Application\Service\SourceSyncOutcome;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Entity\SourceOAuthAccount;
use Packio\Source\Domain\Exception\SourceTokenMissingException;
use Packio\Source\Domain\Exception\SourceTokenRefreshFailedException;
use Packio\Source\Domain\Exception\SourceTokenRefreshUnavailableException;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceOAuthAccountRepositoryInterface;
use Packio\Source\Domain\Repository\SourceProviderInstanceRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Source\Domain\Service\SourceAuthorizationStrategyResolver;
use PackioTest\Support\Fake\InertSourceCollaborators;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(SourceSyncExecutionService::class)]
#[AllowMockObjectsWithoutExpectations]
final class SourceSyncExecutionServiceTest extends TestCase
{
    use InertSourceCollaborators;

    private MockObject&PublicationSyncPort $publicationSyncPort;
    private MockObject&SourceRepositoryInterface $sourceRepository;
    private MockObject&ProviderAuthorizationRepositoryInterface $providerAuthorizationRepository;
    private MockObject&SourceOAuthAccountRepositoryInterface $sourceOAuthAccountRepository;
    private JobSchedulerPort&MockObject $jobApiFacade;
    private LoggerInterface&MockObject $jobLogger;

    protected function setUp(): void
    {
        $this->publicationSyncPort             = $this->createMock(PublicationSyncPort::class);
        $this->sourceRepository                = $this->createMock(SourceRepositoryInterface::class);
        $this->providerAuthorizationRepository = $this->createMock(ProviderAuthorizationRepositoryInterface::class);
        $this->sourceOAuthAccountRepository    = $this->createMock(SourceOAuthAccountRepositoryInterface::class);
        $this->jobApiFacade                    = $this->createMock(JobSchedulerPort::class);
        $this->jobLogger                       = $this->createMock(LoggerInterface::class);
        $this->sourceRepository->method('findByIdInOrganization')->willReturn($this->source());
    }

    /** @param 'error'|'warning' $level */
    #[Test]
    #[DataProvider('authorizationFailures')]
    public function authorizationFailureMarksAuthorizationAndOAuthAccountInactiveWithoutRetry(
        RuntimeException $runtimeException,
        string $level,
        string $log,
        string $type,
        string $prefix
    ): void {
        $source                = $this->source();
        $providerAuthorization = $this->providerAuthorization();
        $sourceOAuthAccount    = $this->sourceOAuthAccount();

        $this->publicationSyncPort->expects(self::once())
            ->method('synchronizeSource')
            ->with('source-1')
            ->willThrowException($runtimeException)
        ;
        $this->jobLogger->expects(self::never())->method('info');
        $this->jobLogger->expects(self::once())
            ->method($level)
            ->with(
                $log,
                self::callback(static fn (array $context): bool => $type === $context['auth_failure_type']),
            )
        ;
        $this->sourceRepository->expects(self::exactly(2))
            ->method('findById')
            ->with('source-1')
            ->willReturn($source)
        ;
        $this->providerAuthorizationRepository->expects(self::once())
            ->method('findByIdInOrganization')
            ->with('auth-1', 'org-1')
            ->willReturn($providerAuthorization)
        ;
        $this->providerAuthorizationRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (ProviderAuthorization $providerAuthorization): bool => 'inactive' === $providerAuthorization->status()))
            ->willReturnCallback(static fn (ProviderAuthorization $providerAuthorization): ProviderAuthorization => $providerAuthorization)
        ;
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('findById')
            ->with('soa-1')
            ->willReturn($sourceOAuthAccount)
        ;
        $this->sourceOAuthAccountRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (SourceOAuthAccount $sourceOAuthAccount): bool => ! $sourceOAuthAccount->isActive()))
        ;
        $this->sourceRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn (Source $savedSource): bool => 'failed' === $savedSource->lastSyncStatus()
                && $prefix . $runtimeException->getMessage() === $savedSource->lastSyncError()
            ))
            ->willReturnCallback(static fn (Source $savedSource): Source => $savedSource)
        ;

        $sourceSyncExecutionResult = $this->handler()->execute('source-1', 'org-1', $this->jobLogger);

        self::assertSame(SourceSyncOutcome::AuthorizationRequired, $sourceSyncExecutionResult->outcome);
        self::assertSame($runtimeException->getMessage(), $sourceSyncExecutionResult->error);
    }

    /** @return list<array{RuntimeException, string, string, string, string}> */
    public static function authorizationFailures(): array
    {
        return [
            [new SourceTokenRefreshUnavailableException('reauthorize'), 'warning', 'Source sync failed due to auth requiring reauthorization', 'reauthorization_required', 'Provider authorization requires reconnection: '],
            [new SourceTokenMissingException('missing'), 'warning', 'Source sync failed due to missing auth token', 'token_missing', 'Provider token is missing: '],
            [new SourceTokenRefreshFailedException('refresh failed'), 'error', 'Source sync failed due to token refresh failure', 'refresh_failed', 'Provider token refresh failed: '],
        ];
    }

    #[Test]
    public function transientRefreshFailureKeepsAuthorizationValidAndIsRetryable(): void
    {
        $this->publicationSyncPort->expects(self::once())
            ->method('synchronizeSource')
            ->willThrowException(new SourceTokenRefreshFailedException('upstream timeout', requiresReauthorization: false))
        ;
        $this->sourceRepository->method('findById')->willReturn($this->source());
        $this->providerAuthorizationRepository->expects(self::never())->method('save');
        $this->sourceOAuthAccountRepository->expects(self::never())->method('save');
        $this->sourceRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Source $savedSource): bool => 'failed' === $savedSource->lastSyncStatus()))
            ->willReturnCallback(static fn (Source $savedSource): Source => $savedSource)
        ;

        $sourceSyncExecutionResult = $this->handler()->execute('source-1', 'org-1', $this->jobLogger);

        self::assertSame(SourceSyncOutcome::RetryableFailure, $sourceSyncExecutionResult->outcome);
        self::assertSame('upstream timeout', $sourceSyncExecutionResult->error);
    }

    #[Test]
    public function failureResultErrorIsRedacted(): void
    {
        $this->publicationSyncPort->method('synchronizeSource')->willThrowException(new RuntimeException('clone https://user:secrettoken@host/repo.git failed'));

        $sourceSyncExecutionResult = $this->handler()->execute('source-1', 'org-1', $this->jobLogger);

        self::assertSame(SourceSyncOutcome::RetryableFailure, $sourceSyncExecutionResult->outcome);
        self::assertStringNotContainsString('secrettoken', (string) $sourceSyncExecutionResult->error);
    }

    #[Test]
    public function readmeSyncFailureIsLoggedAsNonFatalWarning(): void
    {
        $source   = $this->source();
        $infoLogs = [];

        $this->publicationSyncPort->expects(self::once())
            ->method('synchronizeSource')
            ->with('source-1')
            ->willReturn(new SourceSyncResult(
                packageName: 'vendor/package',
                releases: [
                    [
                        'version'   => '1.0.0',
                        'reference' => 'ref-1',
                    ],
                ],
                created: false,
                readmeSync: [
                    'status'  => 'failed',
                    'message' => 'GitLab README API error (500).',
                ],
            ))
        ;
        $this->jobLogger->expects(self::once())
            ->method('info')
            ->willReturnCallback(static function(string $message, array $context = []) use (&$infoLogs): void {
                $infoLogs[] = [$message, $context];
            })
        ;
        $this->jobLogger->expects(self::once())
            ->method('warning')
            ->with(
                'Source README sync failed',
                self::callback(static fn (array $context): bool => 'failed' === $context['readme_status']
                    && 'README synchronization failed. Existing README was left unchanged.' === $context['readme_message']
                    && 'vendor/package' === $context['package_name']),
            )
        ;
        $this->sourceRepository->expects(self::once())
            ->method('findById')
            ->with('source-1')
            ->willReturn($source)
        ;
        $this->sourceRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Source $savedSource): bool => 'success' === $savedSource->lastSyncStatus()))
            ->willReturnCallback(static fn (Source $savedSource): Source => $savedSource)
        ;
        $this->jobApiFacade->expects(self::once())
            ->method('enqueue')
            ->with(
                'org-1',
                'store.release.archive',
                [
                    'organization_id' => 'org-1',
                    'package_name'    => 'vendor/package',
                    'version'         => '1.0.0',
                    'source_id'       => 'source-1',
                    'reference'       => 'ref-1',
                ],
            )
        ;

        $sourceSyncExecutionResult = $this->handler()->execute('source-1', 'org-1', $this->jobLogger);

        self::assertContains($sourceSyncExecutionResult->outcome, [SourceSyncOutcome::Synchronized, SourceSyncOutcome::Degraded]);
        self::assertSame('Source sync completed', $infoLogs[0][0]);
        self::assertSame(1, $sourceSyncExecutionResult->data['archive_job_count'] ?? null);
        self::assertSame('synchronized', $sourceSyncExecutionResult->data['outcome']);
        self::assertSame(0, $sourceSyncExecutionResult->data['archive_job_failed_count']);
    }

    #[Test]
    public function archiveJobEnqueueFailureDoesNotFailCompletedSourceSync(): void
    {
        $source   = $this->source();
        $infoLogs = [];

        $this->publicationSyncPort->expects(self::once())
            ->method('synchronizeSource')
            ->with('source-1')
            ->willReturn(new SourceSyncResult(
                packageName: 'vendor/package',
                releases: [
                    [
                        'version'   => '1.0.0',
                        'reference' => 'ref-1',
                    ],
                ],
                created: false,
            ))
        ;
        $this->jobLogger->expects(self::once())
            ->method('info')
            ->willReturnCallback(static function(string $message, array $context = []) use (&$infoLogs): void {
                $infoLogs[] = [$message, $context];
            })
        ;
        $this->jobLogger->expects(self::once())
            ->method('warning')
            ->with(
                'Archive storage job enqueue failed',
                self::callback(static fn (array $context): bool => 'source-1' === $context['source_id']
                    && 'vendor/package' === $context['package_name']
                    && '1.0.0' === $context['version']
                    && 'queue unavailable' === $context['error']),
            )
        ;
        $this->sourceRepository->expects(self::once())
            ->method('findById')
            ->with('source-1')
            ->willReturn($source)
        ;
        $this->sourceRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Source $savedSource): bool => 'success' === $savedSource->lastSyncStatus()))
            ->willReturnCallback(static fn (Source $savedSource): Source => $savedSource)
        ;
        $this->jobApiFacade->expects(self::once())
            ->method('enqueue')
            ->willThrowException(new RuntimeException('queue unavailable'))
        ;

        $sourceSyncExecutionResult = $this->handler()->execute('source-1', 'org-1', $this->jobLogger);

        self::assertContains($sourceSyncExecutionResult->outcome, [SourceSyncOutcome::Synchronized, SourceSyncOutcome::Degraded]);
        self::assertSame('Source sync completed', $infoLogs[0][0]);
        self::assertSame(0, $sourceSyncExecutionResult->data['archive_job_count'] ?? null);
        self::assertSame('degraded_success', $sourceSyncExecutionResult->data['outcome']);
        self::assertSame(1, $sourceSyncExecutionResult->data['archive_job_failed_count']);
        self::assertSame('degraded_success', $infoLogs[0][1]['outcome']);
    }

    #[Test]
    public function repeatedSyncRepairsArchiveScheduling(): void
    {
        $source = $this->source();
        $this->sourceRepository->method('findById')->willReturn($source);
        $this->sourceRepository->method('save')->willReturn($source);
        $this->publicationSyncPort->expects(self::exactly(2))->method('synchronizeSource')
            ->willReturn(new SourceSyncResult('vendor/package', [[
                'version' => '1.0.0',
            ]], false))
        ;
        $attempt = 0;
        $this->jobApiFacade->expects(self::exactly(2))->method('enqueue')
            ->willReturnCallback(static function() use (&$attempt): void {
                if (0 === $attempt++) {
                    throw new RuntimeException('queue unavailable');
                }
            })
        ;
        $handler = $this->handler();
        self::assertSame('degraded_success', $handler->execute('source-1', 'org-1', $this->jobLogger)->data['outcome']);
        self::assertSame('synchronized', $handler->execute('source-1', 'org-1', $this->jobLogger)->data['outcome']);
        self::assertSame('success', $source->lastSyncStatus());
    }

    #[Test]
    public function missingSourceDoesNotSynchronizeOrScheduleArchives(): void
    {
        $this->sourceRepository = $this->createMock(SourceRepositoryInterface::class);
        $this->sourceRepository->expects(self::once())->method('findByIdInOrganization')->with('source-1', 'org-1')->willReturn(null);
        $this->publicationSyncPort->expects(self::never())->method('synchronizeSource');
        $this->jobApiFacade->expects(self::never())->method('enqueue');
        self::assertSame(SourceSyncOutcome::NotFound, $this->handler()->execute('source-1', 'org-1', $this->jobLogger)->outcome);
    }

    #[Test]
    public function providerFailureMarksSyncFailedAndAllowsRetry(): void
    {
        $source = $this->source();
        $this->sourceRepository->method('findById')->willReturn($source);
        $this->sourceRepository->expects(self::once())->method('save')->willReturn($source);
        $this->publicationSyncPort->method('synchronizeSource')->willThrowException(new RuntimeException('Provider unavailable'));
        $this->jobApiFacade->expects(self::never())->method('enqueue');
        self::assertSame(SourceSyncOutcome::RetryableFailure, $this->handler()->execute('source-1', 'org-1', $this->jobLogger)->outcome);
        self::assertSame('failed', $source->lastSyncStatus());
    }

    #[Test]
    public function syncErrorIsRedactedBeforeSaving(): void
    {
        $source = $this->source();
        $this->sourceRepository->method('findById')->willReturn($source);
        $this->sourceRepository->expects(self::once())->method('save')->willReturn($source);
        $message = 'GET https://x.test/repo?access_token=abc123 failed';
        $this->publicationSyncPort->method('synchronizeSource')->willThrowException(new RuntimeException($message));
        $this->handler()->execute('source-1', 'org-1', $this->jobLogger);
        self::assertSame(SafeExceptionContext::redact($message), $source->lastSyncError());
    }

    #[Test]
    public function partialArchiveFailureDoesNotPreventLaterReleasesFromBeingScheduled(): void
    {
        $source = $this->source();
        $this->sourceRepository->method('findById')->willReturn($source);
        $this->sourceRepository->method('save')->willReturn($source);
        $this->publicationSyncPort->method('synchronizeSource')->willReturn(new SourceSyncResult('vendor/package', [
            [
                'version' => '',
            ], [
                'version' => null,
            ], [], [
                'version' => '1.0.0',
            ], [
                'version' => '2.0.0',
            ],
        ], false));
        $this->jobApiFacade->expects(self::exactly(2))->method('enqueue')->willReturnCallback(static function(string $org, string $type, array $payload): void {
            if ('1.0.0' === $payload['version']) {
                throw new RuntimeException('queue unavailable');
            }
            self::assertSame('2.0.0', $payload['version']);
        });
        $sourceSyncExecutionResult = $this->handler()->execute('source-1', 'org-1', $this->jobLogger);
        self::assertSame(SourceSyncOutcome::Degraded, $sourceSyncExecutionResult->outcome);
        self::assertSame(1, $sourceSyncExecutionResult->data['archive_job_count']);
        self::assertSame(1, $sourceSyncExecutionResult->data['archive_job_failed_count']);
    }

    private function handler(): SourceSyncExecutionService
    {
        return new SourceSyncExecutionService(
            $this->publicationSyncPort,
            $this->commandService(),
            $this->jobApiFacade,
            $this->sourceRepository,
        );
    }

    private function commandService(): SourceCommandService
    {
        $secretEncryptor = $this->createMock(SecretEncryptorInterface::class);

        $sourceOAuthService = new SourceOAuthService(
            $this->createMock(OAuthProviderPort::class),
            $this->sourceOAuthAccountRepository,
            new SourceOAuthAccountSecrets($secretEncryptor),
            $this->createMock(OrganizationQueryPort::class),
            $this->createStub(SourceProviderInstanceRepositoryInterface::class),
            $this->inertSourceProviderInstanceOAuthConfig(),
        );

        return new SourceCommandService(
            $this->sourceRepository,
            $this->providerAuthorizationRepository,
            $this->createMock(SourceProviderRegistryInterface::class),
            new ProviderAuthorizationFactory(new ProviderAuthorizationSecrets($secretEncryptor)),
            new ProviderAuthorizationSecrets($secretEncryptor),
            new SourceAuthorizationStrategyResolver(),
            new SourceMaintenanceService($this->sourceRepository),
            $this->createMock(SourceOAuthAccountOwnershipVerifierInterface::class),
            $this->createMock(SourceProviderInstanceRepositoryInterface::class),
            sourceAuthorizationInvalidationService: new SourceAuthorizationInvalidationService(
                $this->sourceRepository,
                $this->providerAuthorizationRepository,
                $sourceOAuthService,
            ),
            webhookAutoRegistrationService: $this->inertWebhookAutoRegistrationService(),
            sourceWebhookSecrets: new SourceWebhookSecrets(new SodiumSecretEncryptor('test-webhook-key')),
        );
    }

    private function source(): Source
    {
        return new Source(
            organizationId: 'org-1',
            provider: 'github',
            canonicalUrl: 'https://github.com/acme/package',
            displayName: 'acme/package',
            providerAuthorizationId: 'auth-1',
        );
    }

    private function providerAuthorization(): ProviderAuthorization
    {
        return ProviderAuthorization::authorizeFromOAuth(
            organizationId: 'org-1',
            provider: 'github',
            sourceOAuthAccountId: 'soa-1',
            authStrategy: 'github_token',
            configuration: [
                'token' => 'enc_token',
            ],
        );
    }

    private function sourceOAuthAccount(): SourceOAuthAccount
    {
        return new SourceOAuthAccount(
            userId: 'user-1',
            provider: 'github',
            providerUserId: 'provider-user-1',
            accessToken: 'enc_token',
        );
    }
}
