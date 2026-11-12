<?php

declare(strict_types=1);

namespace PackioTest\Feature\Source;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\Driver\Driver;
use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageCleanupApiFacadeInterface;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\ProviderAuthorizationRequestDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceProviderInstanceRequestDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Api\SourceSecretResolverInterface;
use Packio\Source\Application\Command\SourceCommandService;
use Packio\Source\Application\Query\SourceQueryService;
use Packio\Source\Application\Service\SourceJobScheduler;
use Packio\Source\Domain\Entity\ProviderAuthorization;
use Packio\Source\Domain\Repository\ProviderAuthorizationRepositoryInterface;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Builder\ReleaseBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\AbstractLogger;
use Ramsey\Uuid\Uuid;
use ReflectionProperty;
use RuntimeException;
use Stringable;

use function array_column;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function bin2hex;
use function count;
use function getenv;
use function is_null;
use function putenv;
use function random_bytes;
use function str_contains;
use function str_starts_with;
use function ucfirst;

final class SourceFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    private FakeArchiveStorageCleanupApiFacade $fakeArchiveStorageCleanupApiFacade;

    /** @var array<string, ?string> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = [
            'SOURCE_PROVIDER_BITBUCKET_ENABLED'          => $this->envValue('SOURCE_PROVIDER_BITBUCKET_ENABLED'),
            'SOURCE_PROVIDER_GITLAB_SELF_HOSTED_ENABLED' => $this->envValue('SOURCE_PROVIDER_GITLAB_SELF_HOSTED_ENABLED'),
        ];

        $this->setEnv('SOURCE_PROVIDER_BITBUCKET_ENABLED', 'true');
        $this->setEnv('SOURCE_PROVIDER_GITLAB_SELF_HOSTED_ENABLED', 'true');
        $this->fakeArchiveStorageCleanupApiFacade                               = new FakeArchiveStorageCleanupApiFacade();
        $this->serviceOverrides[ArchiveStorageCleanupApiFacadeInterface::class] = $this->fakeArchiveStorageCleanupApiFacade;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->originalEnv as $key => $value) {
            if (null === $value) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);

                continue;
            }

            $this->setEnv($key, $value);
        }
    }

    #[Test]
    public function itLoadsSourcesIndexForOwner(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization);

        $this->logIn($owner->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/sources', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Sources/Index');

        $props = $testResponse->json()['props'] ?? [];
        self::assertIsArray($props);
        self::assertTrue($props['can_manage_sources'] ?? false);

        $sourceIds = array_column($props['sources'] ?? [], 'id');
        self::assertContains($sourceDto->id, $sourceIds);

        $sourcePayload = array_values(array_filter(
            $props['sources'] ?? [],
            static fn (array $item): bool => ($item['id'] ?? '') === $sourceDto->id,
        ));
        self::assertCount(1, $sourcePayload);
        self::assertArrayHasKey('webhook_secret', $sourcePayload[0]);
    }

    #[Test]
    public function sourcesPageKeepsProjectSettingsQueriesBounded(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $this->createSourceProviderInstance($organization, $owner->id);
        $this->createSource($organization);

        $this->logIn($owner->email);

        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $driver = $databaseProvider->database()->getDriver();
        self::assertInstanceOf(Driver::class, $driver);

        $reflectionProperty = new ReflectionProperty(Driver::class, 'logger');
        $originalLogger     = $reflectionProperty->getValue($driver);
        $logger             = new class extends AbstractLogger {
            public int $count = 0;

            public function log($level, string|Stringable $message, array $context = []): void
            {
                if (str_contains((string) $message, 'project_settings')) {
                    ++$this->count;
                }
            }
        };

        $driver->setLogger($logger);

        try {
            $this->get('/organization/' . $organization->slug . '/sources', [
                'Accept'    => 'application/json',
                'X-Inertia' => 'true',
            ])->assertStatus(200);
        } finally {
            $reflectionProperty->setValue($driver, $originalLogger);
        }

        // 16 from OAuth provider availability (2 calls x 8), 18 from Source's own provider overrides (to be reduced in stage 06).
        self::assertLessThanOrEqual(34, $logger->count);
    }

    #[Test]
    public function itProvidesAndCleansUpSourceProviderInstanceDependenciesForAnOwner(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $instanceId             = $this->createSourceProviderInstance($organization, $owner->id);

        /** @var SourceApiFacadeInterface $sources */
        $sources                  = $this->getService(SourceApiFacadeInterface::class);
        $providerAuthorizationDto = $sources->authorizeProvider(new ProviderAuthorizationRequestDto(
            'gitlab_self_hosted',
            $organization->id,
            'token',
            [
                'token'                => 'inactive-feature-token',
                'provider_instance_id' => $instanceId,
            ],
        ));

        /** @var ProviderAuthorizationRepositoryInterface $authorizations */
        $authorizations = $this->getService(ProviderAuthorizationRepositoryInterface::class);
        $authorization  = $authorizations->findByIdInOrganization($providerAuthorizationDto->id, $organization->id);
        self::assertNotNull($authorization);
        $authorization->updateStatus('inactive');
        $authorizations->save($authorization);

        $this->logIn($owner->email);

        $this->get('/organization/' . $organization->slug . '/source-provider-instances/' . $instanceId . '/dependencies', [
            'Accept' => 'application/json',
        ])
            ->assertStatus(200)
            ->assertJsonPath('instance.id', $instanceId)
            ->assertJsonPath('source_count', 0)
            ->assertJsonPath('inactive_authorization_count', 1)
            ->assertJsonPath('active_authorization_count', 0)
        ;

        $this->withCsrf('/organization/' . $organization->slug . '/sources')->postJson(
            '/organization/' . $organization->slug . '/source-provider-instances/' . $instanceId . '/delete-inactive-authorizations',
            [],
            [
                'Accept' => 'application/json',
            ],
        )
            ->assertStatus(200)
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('deleted_inactive_authorizations_count', 1)
            ->assertJsonPath('deleted_source_oauth_accounts_count', 0)
        ;
    }

    #[Test]
    public function itReturnsStructuredConflictDependenciesForAnActiveAuthorization(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $instanceId             = $this->createSourceProviderInstance($organization, $owner->id);

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        $sources->authorizeProvider(new ProviderAuthorizationRequestDto(
            'gitlab_self_hosted',
            $organization->id,
            'token',
            [
                'token'                => 'feature-instance-token',
                'provider_instance_id' => $instanceId,
            ],
        ));

        $this->logIn($owner->email);

        $this->withCsrf('/organization/' . $organization->slug . '/sources')->postJson(
            '/organization/' . $organization->slug . '/source-provider-instances/' . $instanceId . '/delete-inactive-authorizations',
            [],
            [
                'Accept' => 'application/json',
            ],
        )
            ->assertStatus(409)
            ->assertJsonPath('dependencies.instance.id', $instanceId)
            ->assertJsonPath('dependencies.active_authorization_count', 1)
            ->assertJsonPath('dependencies.authorizations.0.status', 'active')
        ;
    }

    #[Test]
    public function itRejectsRegisteringASourceWithAuthorizationFromAnInactiveInstance(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $instanceId             = $this->createSourceProviderInstance($organization, $owner->id);

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        $sources->deactivateSourceProviderInstanceInContext(new ActorContext($organization->id, $owner->id, 'owner'), $instanceId);
        $providerAuthorization = ProviderAuthorization::authorize($organization->id, 'gitlab_self_hosted', 'token', [
            'token' => 'legacy-token',
        ], 'Legacy administrator');
        $providerAuthorization->assignProviderInstance($instanceId);

        /** @var ProviderAuthorizationRepositoryInterface $authorizations */
        $authorizations = $this->getService(ProviderAuthorizationRepositoryInterface::class);
        $authorizations->save($providerAuthorization);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source provider instance is not available.');
        $sources->registerSource(new SourceRegistrationRequestDto(
            'gitlab_self_hosted',
            'https://gitlab.example.test/org/inactive-instance',
            $organization->id,
            providerAuthorizationId: $providerAuthorization->getIdentifier()->toString(),
        ));
    }

    #[Test]
    public function itProtectsSourceProviderInstanceDependencyRoutesByPermissionAndOrganization(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $instanceId                      = $this->createSourceProviderInstance($organization, $owner->id);

        $this->logIn($member->email);
        $this->get('/organization/' . $organization->slug . '/source-provider-instances/' . $instanceId . '/dependencies', [
            'Accept' => 'application/json',
        ])->assertStatus(403);
        $this->withCsrf('/organization/' . $organization->slug . '/sources')->postJson(
            '/organization/' . $organization->slug . '/source-provider-instances/' . $instanceId . '/delete-inactive-authorizations',
            [],
            [
                'Accept' => 'application/json',
            ],
        )->assertStatus(403);
    }

    #[Test]
    public function itReturnsNotFoundForForeignSourceProviderInstanceDependencies(): void
    {
        [$owner, $organization]            = $this->createOrganizationWithOwner();
        [$otherOwner, $otherOrganization]  = $this->createOrganizationWithOwner();
        $foreignInstanceId                 = $this->createSourceProviderInstance($otherOrganization, $otherOwner->id);

        $this->logIn($owner->email);
        $this->get('/organization/' . $organization->slug . '/source-provider-instances/' . $foreignInstanceId . '/dependencies', [
            'Accept' => 'application/json',
        ])->assertStatus(404);
        $this->withCsrf('/organization/' . $organization->slug . '/sources')->postJson(
            '/organization/' . $organization->slug . '/source-provider-instances/' . $foreignInstanceId . '/delete-inactive-authorizations',
            [],
            [
                'Accept' => 'application/json',
            ],
        )->assertStatus(404);
    }

    #[Test]
    public function itRefreshesExistingBitbucketSourceDefaultsWhenReadded(): void
    {
        [, $organization] = $this->createOrganizationWithOwner();

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);

        $sourceDto = $sources->registerSource(new SourceRegistrationRequestDto(
            'bitbucket',
            'https://bitbucket.org/workspace/repo',
            $organization->id,
            'main',
        ));

        /** @var SourceRepositoryInterface $sourceRepository */
        $sourceRepository = $this->getService(SourceRepositoryInterface::class);
        $legacySource     = $sourceRepository->findById($sourceDto->id);
        self::assertNotNull($legacySource);

        $legacyConfig = $legacySource->providerConfig();
        unset($legacyConfig['webhook_secret']);
        $legacySource->replaceProviderConfig($legacyConfig);
        $sourceRepository->save($legacySource);

        $readded = $sources->registerSource(new SourceRegistrationRequestDto(
            'bitbucket',
            'https://bitbucket.org/workspace/repo',
            $organization->id,
            'master',
        ));

        self::assertSame($sourceDto->id, $readded->id);
        self::assertSame('master', $readded->defaultRef);
        self::assertArrayHasKey('webhook_secret', $readded->providerConfig);
        self::assertNotSame('', (string) $readded->providerConfig['webhook_secret']);
    }

    #[Test]
    public function itReassignsExistingSourceToExplicitAuthorizationWhenReadded(): void
    {
        [, $organization] = $this->createOrganizationWithOwner();

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);

        $providerAuthorizationDto = $sources->authorizeProvider(new ProviderAuthorizationRequestDto(
            'codeberg',
            $organization->id,
            'token',
            [
                'token' => 'old-codeberg-token',
            ],
        ));
        $secondAuthorization = $sources->authorizeProvider(new ProviderAuthorizationRequestDto(
            'codeberg',
            $organization->id,
            'token',
            [
                'token' => 'new-codeberg-token',
            ],
        ));

        $sourceDto = $sources->registerSource(new SourceRegistrationRequestDto(
            'codeberg',
            'https://codeberg.org/packio/packio',
            $organization->id,
            'main',
            'private',
            $providerAuthorizationDto->id,
        ));

        $readded = $sources->registerSource(new SourceRegistrationRequestDto(
            'codeberg',
            'https://codeberg.org/packio/packio',
            $organization->id,
            'main',
            'private',
            $secondAuthorization->id,
        ));

        self::assertSame($sourceDto->id, $readded->id);
        self::assertSame($secondAuthorization->id, $readded->providerAuthorizationId);
    }

    #[Test]
    public function itAttemptsWebhookRegistrationBeforeSchedulingInitialSync(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);

        $this->getService(SourceJobScheduler::class)->scheduleSourceCreate([
            'organization_id'           => $organization->id,
            'requested_by_user_id'      => $owner->id,
            'provider'                  => 'github',
            'resource'                  => 'https://github.com/org/' . bin2hex(random_bytes(6)),
            'default_ref'               => 'main',
            'visibility'                => 'public',
            'provider_authorization_id' => '',
            'settings'                  => [],
        ], $organization->id);

        $queued = $jobs->findByOrganization($organization->id, [
            'type'  => 'source.create',
            'limit' => 1,
        ]);
        self::assertCount(1, $queued);

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($queued[0]);

        /** @var JobLogRepositoryInterface $jobLogs */
        $jobLogs  = $this->getService(JobLogRepositoryInterface::class);
        $messages = array_map(
            static fn ($jobLog): string => $jobLog->message(),
            $jobLogs->findByJob($queued[0]->getIdentifier()->toString()),
        );

        $webhookLogIndex = null;
        $syncLogIndex    = null;
        foreach ($messages as $index => $message) {
            if (
                'Webhook registered successfully' === $message
                || 'Webhook already registered' === $message
                || str_starts_with($message, 'Webhook registration failed (non-fatal)')
            ) {
                $webhookLogIndex = $index;
            }

            if ('Source sync scheduled' === $message) {
                $syncLogIndex = $index;
            }
        }

        self::assertNotNull($webhookLogIndex);
        self::assertNotNull($syncLogIndex);
        self::assertLessThan($syncLogIndex, $webhookLogIndex);
    }

    #[Test]
    public function itQueuesRepeatedSourceCreateAttemptsWithSameResource(): void
    {
        [, $organization] = $this->createOrganizationWithOwner();

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);

        $payload = [
            'organization_id'           => $organization->id,
            'requested_by_user_id'      => 'user-1',
            'provider'                  => 'bitbucket',
            'resource'                  => 'https://bitbucket.org/workspace/repo',
            'default_ref'               => 'main',
            'visibility'                => 'public',
            'provider_authorization_id' => 'auth-1',
            'idempotency_key'           => $organization->id . ':bitbucket:https://bitbucket.org/workspace/repo',
            'settings'                  => [],
        ];

        $scheduler = $this->getService(SourceJobScheduler::class);

        $scheduler->scheduleSourceCreate($payload, $organization->id);

        $scheduler->scheduleSourceCreate($payload, $organization->id);

        $queued = $jobs->findByOrganization($organization->id, [
            'type'  => 'source.create',
            'limit' => 10,
        ]);

        self::assertCount(2, $queued);
    }

    #[Test]
    public function itReconnectsSourceAfterDeletion(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $resource               = 'https://github.com/org/reconnect';
        $jobs                   = $this->getService(JobRepositoryInterface::class);
        self::assertInstanceOf(JobRepositoryInterface::class, $jobs);
        $this->logIn($owner->email);
        $url     = '/organization/' . $organization->slug . '/sources/connect';
        $payload = [
            'provider'      => 'github',
            'repositoryUrl' => $resource,
            'authScheme'    => 'none',
            'defaultRef'    => 'main',
        ];
        $this->withCsrf()->post($url, $payload)->assertStatus(302);
        $queued = $this->findSourceCreateJobsByResource($jobs, $organization->id, $resource);
        self::assertCount(1, $queued);
        $oldId        = $queued[0]->getIdentifier()->toString();
        $jobProcessor = $this->getService(JobProcessor::class);
        self::assertInstanceOf(JobProcessor::class, $jobProcessor);
        $jobProcessor->process($queued[0]);
        self::assertSame(Job::STATUS_COMPLETED, $jobs->findById($oldId)?->status());
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sources);
        $source = $this->getService(SourceQueryService::class)->findSourceByUrl($resource, $organization->id);
        self::assertNotNull($source);
        $this->withCsrf()->post('/organization/' . $organization->slug . '/sources/' . $source->getIdentifier()->toString() . '/delete')->assertStatus(302);
        $deletions = $this->findSourceDeleteJobsBySource($jobs, $organization->id, $source->getIdentifier()->toString());
        self::assertCount(1, $deletions);
        $jobProcessor->process($deletions[0]);
        self::assertNull($sources->findSource($source->getIdentifier()->toString()));

        // No source remains, but the completed creation job is retained for history.
        $this->withCsrf()->post($url, $payload)->assertStatus(302);
        $this->withCsrf()->post($url, $payload)->assertStatus(302);
        $queued = $this->findSourceCreateJobsByResource($jobs, $organization->id, $resource);
        self::assertCount(2, $queued);
        self::assertCount(1, array_filter($queued, static fn (Job $job): bool => $job->isPending()));
        self::assertSame(Job::STATUS_COMPLETED, $jobs->findById($oldId)->status());
        foreach ($queued as $job) {
            if ($job->isPending()) {
                $jobProcessor->process($job);
                self::assertSame(Job::STATUS_COMPLETED, $job->status());
            }
        }
        $reconnected = $this->getService(SourceQueryService::class)->findSourceByUrl($resource, $organization->id);
        self::assertNotNull($reconnected);
        self::assertNotSame($source->getIdentifier()->toString(), $reconnected->getIdentifier()->toString());
    }

    #[Test]
    public function itQueuesSourceConnectForOwner(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $resource               = 'https://git.example.test/acme/' . bin2hex(random_bytes(6)) . '.git';

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertEmpty($this->findSourceCreateJobsByResource($jobs, $organization->id, $resource));

        $this->logIn($owner->email);
        $this->withCsrf()->post('/organization/' . $organization->slug . '/sources/connect', [
            'provider'         => 'generic_git',
            'visibility'       => 'private',
            'repositoryUrl'    => $resource,
            'authScheme'       => 'none',
            'composerJsonPath' => 'packages/composer.json',
        ])->assertRedirect('/organization/' . $organization->slug . '/sources');

        $queued = $this->findSourceCreateJobsByResource($jobs, $organization->id, $resource);
        self::assertCount(1, $queued);
        self::assertSame('source.create', $queued[0]->type());
        self::assertSame($organization->id, $queued[0]->payload()['organization_id'] ?? null);
        self::assertSame($owner->id, $queued[0]->payload()['requested_by_user_id'] ?? null);
        self::assertSame('generic_git', $queued[0]->payload()['provider'] ?? null);
        self::assertSame('private', $queued[0]->payload()['visibility'] ?? null);
        self::assertSame('', $queued[0]->payload()['provider_authorization_id'] ?? null);
        self::assertSame('packages/composer.json', $queued[0]->payload()['settings']['composer_json_path'] ?? null);
    }

    #[Test]
    public function itQueuesCodebergSourceConnectForOwner(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $resource               = 'https://codeberg.org/packio/packio';
        $sources                = $this->getService(SourceApiFacadeInterface::class);
        self::assertInstanceOf(SourceApiFacadeInterface::class, $sources);
        $providerAuthorizationDto = $sources->authorizeProvider(new ProviderAuthorizationRequestDto('codeberg', $organization->id, 'token', [
            'token' => 'codeberg-test-token',
        ]));

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertEmpty($this->findSourceCreateJobsByResource($jobs, $organization->id, $resource));

        $this->logIn($owner->email);
        $payload = [
            'provider'              => 'codeberg',
            'authorization_id'      => $providerAuthorizationDto->id,
            'resource_urls'         => [$resource],
            'resource_default_refs' => [
                $resource => 'main',
            ],
            'visibility'            => 'public',
        ];
        $this->withCsrf()->post('/organization/' . $organization->slug . '/sources/connect', $payload)->assertRedirect('/organization/' . $organization->slug . '/sources');

        $queued = $this->findSourceCreateJobsByResource($jobs, $organization->id, $resource);
        self::assertCount(1, $queued);
        self::assertSame('source.create', $queued[0]->type());
        self::assertSame($organization->id, $queued[0]->payload()['organization_id'] ?? null);
        self::assertSame($owner->id, $queued[0]->payload()['requested_by_user_id'] ?? null);
        self::assertSame('codeberg', $queued[0]->payload()['provider'] ?? null);
        self::assertSame('main', $queued[0]->payload()['default_ref'] ?? null);
        self::assertSame('public', $queued[0]->payload()['visibility'] ?? null);
        self::assertSame($providerAuthorizationDto->id, $queued[0]->payload()['provider_authorization_id'] ?? null);

        $this->withCsrf()->post('/organization/' . $organization->slug . '/sources/connect', $payload)->assertRedirect('/organization/' . $organization->slug . '/sources');
        self::assertCount(1, $this->findSourceCreateJobsByResource($jobs, $organization->id, $resource));
    }

    #[Test]
    public function itRejectsSourceConnectForOrganizationMembers(): void
    {
        [, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $resource                  = 'https://git.example.test/acme/' . bin2hex(random_bytes(6)) . '.git';

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);

        $this->logIn($member->email);
        $this->withCsrf()->post('/organization/' . $organization->slug . '/sources/connect', [
            'provider'      => 'generic_git',
            'visibility'    => 'public',
            'repositoryUrl' => $resource,
            'authScheme'    => 'none',
        ])->assertRedirect('/organization/' . $organization->slug);

        self::assertEmpty($this->findSourceCreateJobsByResource($jobs, $organization->id, $resource));
    }

    #[Test]
    public function itHidesWebhookSecretFromMembersOnSourcesIndex(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $sourceDto                       = $this->createSource($organization);

        $this->logIn($member->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/sources', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Sources/Index');

        $props = $testResponse->json()['props'] ?? [];
        self::assertFalse($props['can_manage_sources'] ?? true);
        self::assertIsArray($props['sources']);

        $sourcePayload = array_values(array_filter(
            $props['sources'],
            static fn (array $item): bool => ($item['id'] ?? '') === $sourceDto->id,
        ));

        self::assertCount(1, $sourcePayload);
        self::assertArrayNotHasKey('webhook_secret', $sourcePayload[0]);

        $secretResolver = $this->getService(SourceSecretResolverInterface::class);
        if (! $secretResolver instanceof SourceSecretResolverInterface) {
            return;
        }

        $secret = $secretResolver->resolveWebhookSecret($sourceDto->id, $organization->id);
        if (! is_null($secret)) {
            self::assertStringNotContainsString($secret, $testResponse->body());
        }
    }

    #[Test]
    public function itRejectsSourcesIndexForAuthenticatedNonMembers(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $userDto                = $this->createVerifiedUser('non-member');

        $this->logIn($userDto->email);

        $this->get('/organization/' . $organization->slug . '/sources', [
            'X-Inertia' => 'true',
        ])->assertRedirect('/organizations');
    }

    #[Test]
    public function itLoadsSourceDetailsWithAuthorizationDetailsForOwner(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization);

        $this->getService(SourceCommandService::class)->upsertSourceAuthorization(
            $sourceDto->id,
            'github_token',
            [
                'token' => 'ghp_' . bin2hex(random_bytes(8)),
            ],
        );

        $this->logIn($owner->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/sources/' . $sourceDto->id, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Sources/Show');

        $sourceProps = $testResponse->json()['props']['source'] ?? [];
        self::assertIsArray($sourceProps);
        self::assertSame($sourceDto->id, $sourceProps['id'] ?? null);
        self::assertSame(
            'GitHub authorization configured',
            $sourceProps['credentials']['status_label'] ?? null,
        );
        self::assertSame('github_token', $sourceProps['credentials']['auth_type'] ?? null);
        self::assertStringContainsString('****', $sourceProps['credentials']['masked_value'] ?? '');
        self::assertStringNotContainsString('ghp_', $testResponse->body());
    }

    #[Test]
    public function itLoadsSourceDetailsForOrganizationMembers(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $sourceDto                       = $this->createSource($organization);

        $this->logIn($member->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/sources/' . $sourceDto->id, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Sources/Show');

        $sourceProps = $testResponse->json()['props']['source'] ?? [];
        self::assertIsArray($sourceProps);
        self::assertSame($sourceDto->id, $sourceProps['id'] ?? null);
        self::assertFalse($testResponse->json()['props']['can_manage_sources'] ?? true);
        self::assertArrayNotHasKey('webhook_secret', $sourceProps);
    }

    #[Test]
    public function itRejectsSourceDetailsForAuthenticatedNonMembers(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization);
        $userDto                = $this->createVerifiedUser('non-member');

        $this->logIn($userDto->email);

        $this->get('/organization/' . $organization->slug . '/sources/' . $sourceDto->id, [
            'X-Inertia' => 'true',
        ])->assertRedirect('/organizations');
    }

    #[Test]
    public function itRejectsProviderAuthorizationStoreForOrganizationMembers(): void
    {
        [, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);
        self::assertCount(0, $sourceApi->providerAuthorizations($organization->id));

        $this->logIn($member->email);
        $this->withCsrf()->post('/organization/' . $organization->slug . '/sources/authorizations', [
            'provider'           => 'generic_git',
            'authorizationToken' => 'token-' . bin2hex(random_bytes(6)),
            'authScheme'         => 'bearer',
        ])->assertRedirect('/organization/' . $organization->slug);

        self::assertCount(0, $sourceApi->providerAuthorizations($organization->id));
    }

    #[Test]
    public function itRejectsProviderAuthorizationDeleteForOrganizationMembers(): void
    {
        [, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi                = $this->getService(SourceApiFacadeInterface::class);
        $providerAuthorizationDto = $sourceApi->authorizeProvider(new ProviderAuthorizationRequestDto(
            provider: 'generic_git',
            organizationId: $organization->id,
            authStrategy: 'token',
            payload: [
                'token'       => 'token-' . bin2hex(random_bytes(6)),
                'auth_scheme' => 'bearer',
            ],
        ));

        $this->logIn($member->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/authorizations/' . $providerAuthorizationDto->id . '/delete',
            [
                'newAuthorizationId' => '',
            ],
        )->assertRedirect('/organization/' . $organization->slug);

        self::assertNotNull($this->getService(SourceQueryService::class)->findProviderAuthorization($providerAuthorizationDto->id));
    }

    #[Test]
    public function itRejectsProviderAuthorizationJsonEndpointsForOrganizationMembers(): void
    {
        [, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        $this->logIn($member->email);

        $this->get(
            '/organization/' . $organization->slug . '/sources/authorizations/auth-1/resources?provider=generic_git',
            [
                'Accept' => 'application/json',
            ],
        )
            ->assertStatus(403)
            ->assertJsonPath('error', 'Forbidden')
        ;

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/authorizations/auth-1/check',
            [],
            [
                'Accept' => 'application/json',
            ],
        )
            ->assertStatus(403)
            ->assertJsonPath('error', 'Forbidden')
        ;

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/authorizations/oauth',
            [
                'provider'       => 'github',
                'oauthAccountId' => '11111111-1111-4111-8111-111111111111',
            ],
            [
                'Accept' => 'application/json',
            ],
        )
            ->assertStatus(403)
            ->assertJsonPath('error', 'Forbidden')
        ;
    }

    #[Test]
    public function itRejectsIdentityOAuthAccountForSourceProviderAuthorization(): void
    {
        [$owner,, $organization] = $this->createOrganizationWithOwnerAndMember();

        $this->logIn($owner->email);

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);
        self::assertCount(0, $sourceApi->providerAuthorizations($organization->id, 'github'));

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/authorizations/oauth',
            [
                'provider'       => 'github',
                'oauthAccountId' => '11111111-1111-4111-8111-111111111111',
            ],
            [
                'Accept' => 'application/json',
            ],
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error',
                'Source authorization requires a source OAuth connection. Please reconnect the provider.',
            )
        ;

        self::assertCount(0, $sourceApi->providerAuthorizations($organization->id, 'github'));
    }

    #[Test]
    public function itRedirectsSourceArchiveForMemberAndDoesNotChangeState(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $sourceDto                       = $this->createSource($organization);
        $this->createPackageForSource($organization, $sourceDto, 'vendor/archived-forbidden');

        $this->logIn($member->email);

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/archive',
            [
                'archived' => '1',
            ],
        )->assertRedirect('/organization/' . $organization->slug);

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);
        $reloaded  = $sourceApi->findSource($sourceDto->id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->archived);
    }

    #[Test]
    public function itArchivesSourceAndHidesItsPackagesForOwner(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization);
        $packageName            = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackageForSource($organization, $sourceDto, $packageName);

        $this->logIn($owner->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/archive',
            [
                'archived' => '1',
            ],
        )->assertRedirect('/organization/' . $organization->slug . '/sources/' . $sourceDto->id);

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);
        $reloaded  = $sourceApi->findSource($sourceDto->id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->archived);

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $package  = $packages->findPackageInOrganization($organization->id, $packageName);
        self::assertNotNull($package);
        self::assertFalse($package->active);
    }

    #[Test]
    public function itUpdatesSourceSettingsAndPropagatesVisibilityForOwner(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization);
        $packageName            = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackageForSource($organization, $sourceDto, $packageName);

        $this->logIn($owner->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/settings',
            [
                'visibility' => 'private',
                'active'     => 'on',
            ],
        )->assertRedirect('/organization/' . $organization->slug . '/sources/' . $sourceDto->id);

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);
        $reloaded  = $sourceApi->findSource($sourceDto->id);
        self::assertNotNull($reloaded);
        self::assertSame('private', $reloaded->visibility);

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $package  = $packages->findPackageInOrganization($organization->id, $packageName);
        self::assertNotNull($package);
        self::assertSame('private', $package->visibility);
    }

    #[Test]
    public function itRejectsSourceSettingsUpdateForOrganizationMembers(): void
    {
        [, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $sourceDto                 = $this->createSource($organization);
        $packageName               = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackageForSource($organization, $sourceDto, $packageName);

        $this->logIn($member->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/settings',
            [
                'visibility' => 'private',
                'active'     => 'on',
            ],
        )->assertRedirect('/organization/' . $organization->slug);

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);
        $reloaded  = $sourceApi->findSource($sourceDto->id);
        self::assertNotNull($reloaded);
        self::assertSame('public', $reloaded->visibility);

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $package  = $packages->findPackageInOrganization($organization->id, $packageName);
        self::assertNotNull($package);
        self::assertSame('public', $package->visibility);
    }

    #[Test]
    public function itUpdatesSourcePackagesActivityFromSourceManagementPage(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization);
        $packageName            = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackageForSource($organization, $sourceDto, $packageName);

        $this->logIn($owner->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/packages/activity',
            [
                'active' => '0',
            ],
        )->assertRedirect('/organization/' . $organization->slug . '/sources/' . $sourceDto->id);

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $package  = $packages->findPackageInOrganization($organization->id, $packageName);
        self::assertNotNull($package);
        self::assertFalse($package->active);
    }

    #[Test]
    public function itRotatesWebhookSecretForOwner(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization);
        $oldSecret              = (string) ($sourceDto->providerConfig['webhook_secret'] ?? '');
        self::assertNotSame('', $oldSecret);

        $this->logIn($owner->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/webhook/rotate',
        )->assertRedirect('/organization/' . $organization->slug . '/sources/' . $sourceDto->id);

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);
        $reloaded  = $sourceApi->findSource($sourceDto->id);
        self::assertNotNull($reloaded);
        self::assertNotSame('', (string) ($reloaded->providerConfig['webhook_secret'] ?? ''));
        self::assertNotSame($oldSecret, $reloaded->providerConfig['webhook_secret'] ?? '');
    }

    #[Test]
    public function itRejectsWebhookSecretRotationForOrganizationMembers(): void
    {
        [, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $sourceDto                 = $this->createSource($organization);
        $oldSecret                 = (string) ($sourceDto->providerConfig['webhook_secret'] ?? '');

        $this->logIn($member->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/webhook/rotate',
        )->assertRedirect('/organization/' . $organization->slug);

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);
        $reloaded  = $sourceApi->findSource($sourceDto->id);
        self::assertNotNull($reloaded);
        self::assertSame($oldSecret, $reloaded->providerConfig['webhook_secret'] ?? '');
    }

    #[Test]
    public function itHandlesUnsupportedWebhookRegistrationForOwner(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createGenericGitSource($organization);

        $this->logIn($owner->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/webhook/register',
        )->assertRedirect('/organization/' . $organization->slug . '/sources/' . $sourceDto->id);
    }

    #[Test]
    public function itRejectsWebhookRegistrationForOrganizationMembers(): void
    {
        [, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $sourceDto                 = $this->createGenericGitSource($organization);

        $this->logIn($member->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/webhook/register',
        )->assertRedirect('/organization/' . $organization->slug);
    }

    #[Test]
    public function itDisplaysRecentAuditActivityInSourceDetailsPage(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization, 'source-with-audit-' . bin2hex(random_bytes(6)));

        /** @var AuditApiFacadeInterface $auditApi */
        $auditApi = $this->getService(AuditApiFacadeInterface::class);
        $auditApi->record(
            'source.sync',
            [
                'phase' => 'test',
            ],
            $organization->id,
            $sourceDto->id,
            '',
            $owner->id,
        );
        $auditApi->record(
            'package.verified',
            [
                'package' => 'vendor/example/package',
            ],
            $organization->id,
            $sourceDto->id,
            'vendor/example/package',
            $owner->id,
        );
        $auditApi->record(
            'package.verified',
            [
                'package' => 'vendor/example/package',
            ],
            $organization->id,
            $sourceDto->id,
            '',
            $owner->id,
        );

        $this->logIn($owner->email);

        $testResponse = $this->get('/organization/' . $organization->slug . '/sources/' . $sourceDto->id, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Sources/Show');

        $auditTimeline = $testResponse->json()['props']['audit'] ?? [];
        self::assertIsArray($auditTimeline);
        self::assertGreaterThanOrEqual(2, count($auditTimeline));

        $events = array_column($auditTimeline, 'event');
        self::assertContains('source.sync', $events);
        self::assertContains('package.verified', $events);

        $packageVerifiedIds = [];
        foreach ($auditTimeline as $entry) {
            if ('package.verified' === ($entry['event'] ?? null)) {
                $packageVerifiedIds[] = $entry['id'] ?? null;
            }
        }

        self::assertCount(2, $packageVerifiedIds);
        foreach ($packageVerifiedIds as $packageVerifiedId) {
            self::assertIsString($packageVerifiedId);
            self::assertTrue(Uuid::isValid($packageVerifiedId));
        }
        self::assertSame(2, count(array_unique($packageVerifiedIds)));

        foreach ($auditTimeline as $entry) {
            self::assertArrayNotHasKey('context', $entry);
        }
    }

    #[Test]
    #[DataProvider('sourceProviderNames')]
    public function itDeletesSourceAndRelatedArtifactsForOwner(string $provider): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization, provider: $provider);
        $packageName            = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackageForSource($organization, $sourceDto, $packageName);
        $this->publishRelease($packageName, '1.0.0', $organization->id);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertEmpty(
            $this->findSourceDeleteJobsBySource($jobs, $organization->id, $sourceDto->id),
            'Expected no delete jobs before source deletion.',
        );

        $this->logIn($owner->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/delete',
        )->assertRedirect('/organization/' . $organization->slug . '/sources');

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi    = $this->getService(SourceApiFacadeInterface::class);
        $queuedSource = $sourceApi->findSource($sourceDto->id);
        self::assertNotNull($queuedSource);
        self::assertSame('deleting', $queuedSource->status);
        self::assertFalse($queuedSource->archived);
        self::assertFalse($queuedSource->active);

        $testResponse = $this->get('/organization/' . $organization->slug . '/sources', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $testResponse->assertStatus(200)->assertInertiaComponent('Sources/Index');
        self::assertSame(
            'Source will be permanently deleted.',
            $testResponse->json()['flash']['toasts'][0]['message'] ?? null,
        );
        self::assertNotContains(
            $sourceDto->id,
            array_column($testResponse->json()['props']['sources'] ?? [], 'id'),
        );

        $this->get('/organization/' . $organization->slug . '/sources/' . $sourceDto->id)
            ->assertRedirect('/organization/' . $organization->slug . '/sources')
        ;

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        self::assertNotNull($packages->findPackageInOrganization($organization->id, $packageName));

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        self::assertNotNull($releases->findRelease($packageName, '1.0.0', $organization->id));
        self::assertSame([], $this->fakeArchiveStorageCleanupApiFacade->calls);

        $deleteJobs = $this->findSourceDeleteJobsBySource($jobs, $organization->id, $sourceDto->id);
        self::assertCount(1, $deleteJobs);
        self::assertSame('source.delete', $deleteJobs[0]->type());
        self::assertSame(
            'source-delete:' . $sourceDto->id . ':' . ($deleteJobs[0]->payload()['deletion_run_id'] ?? ''),
            $deleteJobs[0]->idempotencyKey(),
        );
        self::assertSame($sourceDto->id, $deleteJobs[0]->payload()['source_id'] ?? null);
        self::assertSame($organization->id, $deleteJobs[0]->payload()['organization_id'] ?? null);

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($deleteJobs[0]);

        self::assertNull($sourceApi->findSource($sourceDto->id));
        self::assertNull($packages->findPackageInOrganization($organization->id, $packageName));
        self::assertNull($releases->findRelease($packageName, '1.0.0', $organization->id));
        self::assertSame(
            [[$organization->id, $packageName]],
            $this->fakeArchiveStorageCleanupApiFacade->calls,
        );
    }

    /**
     * @return iterable<string, array{provider: string}>
     */
    public static function sourceProviderNames(): iterable
    {
        yield 'github' => [
            'provider' => 'github',
        ];

        yield 'gitlab' => [
            'provider'             => 'gitlab',
        ];

        yield 'gitlab_self_hosted' => [
            'provider' => 'gitlab_self_hosted',
        ];

        yield 'generic_git' => [
            'provider'   => 'generic_git',
        ];

        yield 'bitbucket' => [
            'provider' => 'bitbucket',
        ];

        yield 'codeberg' => [
            'provider' => 'codeberg',
        ];
    }

    #[Test]
    public function itKeepsSourceMetadataWhenArchiveStorageCleanupFails(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization);
        $packageName            = 'vendor/' . bin2hex(random_bytes(6));
        $this->createPackageForSource($organization, $sourceDto, $packageName);
        $this->publishRelease($packageName, '1.0.0', $organization->id);
        $this->fakeArchiveStorageCleanupApiFacade->shouldFail = true;

        $this->logIn($owner->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/delete',
        )->assertRedirect('/organization/' . $organization->slug . '/sources');

        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        self::assertNotNull($packages->findPackageInOrganization($organization->id, $packageName));

        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        self::assertNotNull($releases->findRelease($packageName, '1.0.0', $organization->id));
        self::assertSame([], $this->fakeArchiveStorageCleanupApiFacade->calls);

        /** @var JobRepositoryInterface $jobs */
        $jobs       = $this->getService(JobRepositoryInterface::class);
        $deleteJobs = $this->findSourceDeleteJobsBySource($jobs, $organization->id, $sourceDto->id);
        self::assertCount(1, $deleteJobs);

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($deleteJobs[0]);

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi       = $this->getService(SourceApiFacadeInterface::class);
        $remainingSource = $sourceApi->findSource($sourceDto->id);
        self::assertNotNull($remainingSource);
        self::assertSame('deleting', $remainingSource->status);
        self::assertNotNull($packages->findPackageInOrganization($organization->id, $packageName));
        self::assertNotNull($releases->findRelease($packageName, '1.0.0', $organization->id));
        self::assertSame(Job::STATUS_PENDING, $deleteJobs[0]->status());
        self::assertSame(
            [[$organization->id, $packageName]],
            $this->fakeArchiveStorageCleanupApiFacade->calls,
        );
    }

    #[Test]
    public function itRejectsSourceDeleteForOrganizationMembers(): void
    {
        [, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $sourceDto                 = $this->createSource($organization);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);

        $this->logIn($member->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/delete',
        )->assertRedirect('/organization/' . $organization->slug);

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);
        $reloaded  = $sourceApi->findSource($sourceDto->id);
        self::assertNotNull($reloaded);
        self::assertSame('active', $reloaded->status);
        self::assertTrue($reloaded->active);
        self::assertEmpty($this->findSourceDeleteJobsBySource($jobs, $organization->id, $sourceDto->id));
    }

    #[Test]
    public function itQueuesSourceResyncForOwner(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertEmpty(
            $this->findSourceSyncJobsBySource($jobs, $organization->id, $sourceDto->id),
            'Expected clean job state before resync.',
        );

        $this->logIn($owner->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/resync',
        )->assertRedirect('/organization/' . $organization->slug . '/sources/' . $sourceDto->id);

        $testResponse = $this->get('/organization/' . $organization->slug . '/sources/' . $sourceDto->id, [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        self::assertSame(
            'Re-sync scheduled. Refresh this page to see the latest sync status.',
            $testResponse->json()['flash']['success'] ?? null,
        );

        $requeued = $this->findSourceSyncJobsBySource($jobs, $organization->id, $sourceDto->id);
        self::assertCount(1, $requeued);
        self::assertSame('source.sync', $requeued[0]->type());
        self::assertSame($sourceDto->id, $requeued[0]->payload()['source_id'] ?? null);
        self::assertSame($organization->id, $requeued[0]->payload()['organization_id'] ?? null);
    }

    #[Test]
    public function itDoesNotQueueSourceResyncForInactiveSource(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $sourceDto              = $this->createSource($organization);
        $this->logIn($owner->email);

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/archive',
            [
                'archived' => '1',
            ],
        )->assertRedirect('/organization/' . $organization->slug . '/sources/' . $sourceDto->id);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertEmpty(
            $this->findSourceSyncJobsBySource($jobs, $organization->id, $sourceDto->id),
            'Expected no jobs before resync attempt.',
        );

        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/resync',
        )->assertRedirect('/organization/' . $organization->slug . '/sources/' . $sourceDto->id);

        self::assertEmpty(
            $this->findSourceSyncJobsBySource($jobs, $organization->id, $sourceDto->id),
            'Inactive source should not schedule sync job.',
        );
    }

    #[Test]
    public function itRejectsSourceResyncForOrganizationMembers(): void
    {
        [, $member, $organization] = $this->createOrganizationWithOwnerAndMember();
        $sourceDto                 = $this->createSource($organization);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        self::assertEmpty(
            $this->findSourceSyncJobsBySource($jobs, $organization->id, $sourceDto->id),
            'Member should not have existing sync jobs.',
        );

        $this->logIn($member->email);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/sources/' . $sourceDto->id . '/resync',
        )->assertRedirect('/organization/' . $organization->slug);

        self::assertEmpty(
            $this->findSourceSyncJobsBySource($jobs, $organization->id, $sourceDto->id),
            'Member should not be able to enqueue source sync.',
        );
    }

    /**
     * @return array{0: UserDto, 1: OrganizationDto}
     */
    private function createOrganizationWithOwner(): array
    {
        $userDto = $this->createVerifiedUser('owner');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return [
            $userDto,
            $organizations->registerOrganization(
                'source-org-' . bin2hex(random_bytes(6)),
                $userDto->id,
            ),
        ];
    }

    /**
     * @return array{0: UserDto, 1: UserDto, 2: OrganizationDto}
     */
    private function createOrganizationWithOwnerAndMember(): array
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $userDto                = $this->createVerifiedUser('member');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $organizations->addMember($organization->id, $userDto->id, 'member');

        return [$owner, $userDto, $organization];
    }

    private function createVerifiedUser(string $prefix): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            $prefix . '-' . bin2hex(random_bytes(4)) . '@example.test',
            ucfirst($prefix) . ' User',
            self::PASSWORD,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createSource(OrganizationDto $organizationDto, ?string $name = null, string $provider = 'github'): SourceDto
    {
        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);

        $suffix = $name ?? bin2hex(random_bytes(6));

        return $sources->registerSource(new SourceRegistrationRequestDto(
            $provider,
            $this->sourceUrl($provider, $suffix),
            $organizationDto->id,
        ));
    }

    private function createSourceProviderInstance(OrganizationDto $organizationDto, string $ownerId): string
    {
        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);

        return $sources->createSourceProviderInstanceInContext(
            new ActorContext($organizationDto->id, $ownerId, 'owner'),
            new SourceProviderInstanceRequestDto(
                'gitlab_self_hosted',
                'Feature GitLab',
                'https://gitlab-' . bin2hex(random_bytes(6)) . '.example.test',
                '',
                null,
                'token',
            ),
        )->id;
    }

    private function sourceUrl(string $provider, string $suffix): string
    {
        return match ($provider) {
            'github'             => 'https://github.com/org/' . $suffix,
            'gitlab'             => 'https://gitlab.com/org/' . $suffix,
            'gitlab_self_hosted' => 'https://gitlab.example.test/org/' . $suffix,
            'generic_git'        => 'https://git.example.test/acme/' . $suffix . '.git',
            'bitbucket'          => 'https://bitbucket.org/org/' . $suffix,
            'codeberg'           => 'https://codeberg.org/org/' . $suffix,
            default              => throw new RuntimeException('Unknown source provider.'),
        };
    }

    private function createGenericGitSource(OrganizationDto $organizationDto): SourceDto
    {
        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);

        return $sources->registerSource(new SourceRegistrationRequestDto(
            'generic_git',
            'https://git.example.test/acme/' . bin2hex(random_bytes(6)) . '.git',
            $organizationDto->id,
        ));
    }

    private function createPackageForSource(
        OrganizationDto $organizationDto,
        SourceDto $sourceDto,
        string $packageName,
        string $visibility = 'public',
        bool $active = true,
    ): void {
        /** @var PackageApiFacadeInterface $packages */
        $packages = $this->getService(PackageApiFacadeInterface::class);
        $packages->upsert(new PackageDto(
            name: $packageName,
            type: 'library',
            description: 'Package for source feature tests',
            visibility: $visibility,
            sourceType: $sourceDto->provider,
            sourceUrl: $sourceDto->url,
            createdAt: '2026-05-20T00:00:00+00:00',
            updatedAt: '2026-05-20T00:00:00+00:00',
            organizationId: $organizationDto->id,
            active: $active,
            sourceId: $sourceDto->id,
        ));
    }

    private function publishRelease(string $packageName, string $version, string $organizationId): void
    {
        /** @var ReleaseApiFacadeInterface $releases */
        $releases = $this->getService(ReleaseApiFacadeInterface::class);
        $releases->publish(ReleaseBuilder::make([
            'packageName'    => $packageName,
            'version'        => $version,
            'reference'      => 'v' . $version,
            'organizationId' => $organizationId,
        ]));
    }

    private function logIn(string $email): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');
    }

    /**
     * @return list<Job>
     */
    private function findSourceSyncJobsBySource(JobRepositoryInterface $jobRepository, string $organizationId, string $sourceId): array
    {
        $found = [];

        foreach ($jobRepository->findByOrganization($organizationId, [
            'type' => 'source.sync',
        ]) as $job) {
            if (($job->payload()['source_id'] ?? '') === $sourceId) {
                $found[] = $job;
            }
        }

        return $found;
    }

    /**
     * @return list<Job>
     */
    private function findSourceDeleteJobsBySource(JobRepositoryInterface $jobRepository, string $organizationId, string $sourceId): array
    {
        $found = [];

        foreach ($jobRepository->findByOrganization($organizationId, [
            'type' => 'source.delete',
        ]) as $job) {
            if (($job->payload()['source_id'] ?? '') === $sourceId) {
                $found[] = $job;
            }
        }

        return $found;
    }

    /**
     * @return list<Job>
     */
    private function findSourceCreateJobsByResource(JobRepositoryInterface $jobRepository, string $organizationId, string $resource): array
    {
        $found = [];

        foreach ($jobRepository->findByOrganization($organizationId, [
            'type' => 'source.create',
        ]) as $job) {
            if (($job->payload()['resource'] ?? '') === $resource) {
                $found[] = $job;
            }
        }

        return $found;
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }

    private function envValue(string $key): ?string
    {
        $value = getenv($key);

        return false === $value ? null : $value;
    }
}

final class FakeArchiveStorageCleanupApiFacade implements ArchiveStorageCleanupApiFacadeInterface
{
    /** @var list<array{0: string, 1: string}> */
    public array $calls = [];

    public bool $shouldFail = false;

    public function schedulePackageArchiveCleanup(
        string $organizationId,
        string $packageName,
        ?string $deletionRunId = null,
        string $origin = '',
    ): array {
        unset($deletionRunId, $origin);

        $this->calls[] = [$organizationId, $packageName];

        if ($this->shouldFail) {
            throw new RuntimeException('Archive cleanup failed.');
        }

        return [
            'discovered' => 1,
            'scheduled'  => 1,
            'failed'     => 0,
        ];
    }
}
