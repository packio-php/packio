<?php

declare(strict_types=1);

namespace PackioTest\Feature\Webhook;

use Cake\Chronos\Chronos;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PackioTest\Support\Http\WebhookTestHelpers;
use PHPUnit\Framework\Attributes\Test;

use function array_map;
use function array_unique;
use function bin2hex;
use function dirname;
use function hash_hmac;
use function json_encode;
use function random_bytes;

final class WebhookControllerFeatureTest extends FeatureTestCase
{
    use WebhookTestHelpers;

    private const string PASSWORD = 'Password1!';

    protected function setUp(): void
    {
        $config    = require dirname(__DIR__, 3) . '/config/config.php';
        $providers = $config['sources']['providers'] ?? [];
        foreach (['github', 'gitlab', 'bitbucket'] as $provider) {
            $providers[$provider]['enabled'] = true;
        }

        $webhooks           = $config['sources']['webhooks'] ?? [];
        $webhooks['github'] = [
            'signature_header' => 'X-Hub-Signature-256',
            'events'           => ['push', 'create', 'release', 'ping'],
        ];
        $webhooks['gitlab'] = [
            'events' => ['Push Hook'],
        ];
        $webhooks['bitbucket'] = [
            'events' => ['repo:push'],
        ];
        $config['sources']['providers'] = $providers;
        $config['sources']['webhooks']  = $webhooks;

        $this->serviceOverrides['config']                  = $config;
        $this->serviceOverrides['config.sources']          = $config['sources'];
        $this->serviceOverrides['config.sources.webhooks'] = $webhooks;
        parent::setUp();
    }

    #[Test]
    public function itReturnsNotFoundForUnknownSource(): void
    {
        $payload = $this->fixtureJson('github', 'ping', [
            'repository' => [
                'html_url' => 'https://example.com/missing.git',
            ],
        ]);

        $testResponse = $this->request(
            'POST',
            '/webhooks/github/' . bin2hex(random_bytes(16)),
            $payload,
            [
                'X-GitHub-Event'    => 'ping',
                'X-GitHub-Delivery' => $this->webhookDeliveryId(),
            ],
            true,
        );

        $testResponse->assertStatus(404);
        self::assertSame('Source not found.', $testResponse->json()['message'] ?? null);
    }

    #[Test]
    public function itReceivesWebhookPayloadForKnownSource(): void
    {
        $sourceDto = $this->createSource();
        $sourceId  = $sourceDto->id;
        $sourceUrl = $sourceDto->url;
        $payload   = $this->fixtureJson('github', 'ping', [
            'repository' => [
                'html_url' => $sourceUrl,
            ],
        ]);

        $testResponse = $this->request(
            'POST',
            '/webhooks/github/' . $sourceId,
            $payload,
            $this->githubHeaders(
                $payload,
                (string) $sourceDto->providerConfig['webhook_secret'],
                'ping',
            ),
            true,
        );

        $testResponse->assertStatus(202);
        self::assertSame('GitHub webhook ping accepted.', $testResponse->json()['message'] ?? null);
    }

    #[Test]
    public function itSchedulesSourceSyncForValidGithubPushWebhook(): void
    {
        $sourceDto = $this->createSource();
        $sourceId  = $sourceDto->id;
        $sourceUrl = $sourceDto->url;
        $payload   = $this->fixtureJson('github', 'push', [
            'repository' => [
                'html_url' => $sourceUrl,
            ],
        ]);

        $testResponse = $this->request(
            'POST',
            '/webhooks/github/' . $sourceId,
            $payload,
            $this->githubHeaders(
                $payload,
                (string) $sourceDto->providerConfig['webhook_secret'],
                'push',
            ),
            true,
        );

        $testResponse->assertStatus(202);
        self::assertSame('Sync scheduled for ' . $sourceUrl . '.', $testResponse->json()['message'] ?? null);

        /** @var WebhookApiFacadeInterface $webhookApi */
        $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
        $events     = $webhookApi->recentEvents($sourceId, 1);
        self::assertNotEmpty($events);
        self::assertSame('processed', $events[0]->status);
        self::assertNotNull($events[0]->scheduledJobId);

        $jobs = $this->sourceSyncJobs($sourceDto->organizationId);
        self::assertCount(1, $jobs);
        self::assertSame($sourceId, $jobs[0]->payload()['source_id'] ?? null);
    }

    #[Test]
    public function itSchedulesSourceSyncForConfiguredGithubWebhookEvents(): void
    {
        foreach (['create', 'release'] as $githubEvent) {
            $sourceDto = $this->createSource();
            $sourceId  = $sourceDto->id;
            $sourceUrl = $sourceDto->url;
            $payload   = $this->fixtureJson('github', 'push', [
                'repository' => [
                    'html_url' => $sourceUrl,
                ],
            ]);

            $testResponse = $this->request(
                'POST',
                '/webhooks/github/' . $sourceId,
                $payload,
                $this->githubHeaders(
                    $payload,
                    (string) $sourceDto->providerConfig['webhook_secret'],
                    $githubEvent,
                ),
                true,
            );

            $testResponse->assertStatus(202);
            self::assertSame('Sync scheduled for ' . $sourceUrl . '.', $testResponse->json()['message'] ?? null);

            /** @var WebhookApiFacadeInterface $webhookApi */
            $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
            $events     = $webhookApi->recentEvents($sourceId, 1);
            self::assertNotEmpty($events);
            self::assertSame('processed', $events[0]->status);
            self::assertSame($githubEvent, $events[0]->event);
            self::assertNotNull($events[0]->scheduledJobId);

            $jobs = $this->sourceSyncJobs($sourceDto->organizationId);
            self::assertCount(1, $jobs);
            self::assertSame($sourceId, $jobs[0]->payload()['source_id'] ?? null);
        }
    }

    #[Test]
    public function itCoalescesGithubWebhookSyncJobsBySourceProviderAndMinute(): void
    {
        Chronos::setTestNow(Chronos::parse('2026-05-25 12:47:05'));

        try {
            $sourceDto = $this->createSource();
            $sourceId  = $sourceDto->id;
            $sourceUrl = $sourceDto->url;
            $payload   = $this->fixtureJson('github', 'push', [
                'repository' => [
                    'html_url' => $sourceUrl,
                ],
            ]);

            foreach (['release', 'release', 'create', 'push'] as $githubEvent) {
                $testResponse = $this->request(
                    'POST',
                    '/webhooks/github/' . $sourceId,
                    $payload,
                    $this->githubHeaders(
                        $payload,
                        (string) $sourceDto->providerConfig['webhook_secret'],
                        $githubEvent,
                    ),
                    true,
                );

                $testResponse->assertStatus(202);
                self::assertSame('Sync scheduled for ' . $sourceUrl . '.', $testResponse->json()['message'] ?? null);
            }
        } finally {
            Chronos::setTestNow();
        }

        /** @var WebhookApiFacadeInterface $webhookApi */
        $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
        $events     = $webhookApi->recentEvents($sourceId, 4);
        self::assertCount(4, $events);

        $jobs = $this->sourceSyncJobs($sourceDto->organizationId);
        self::assertCount(1, $jobs);

        $jobId = $jobs[0]->getIdentifier()->toString();
        foreach ($events as $event) {
            self::assertSame('processed', $event->status);
            self::assertSame($jobId, $event->scheduledJobId);
        }
    }

    #[Test]
    public function itSchedulesNewSyncWhenWebhookArrivesAfterPreviousSyncCompleted(): void
    {
        Chronos::setTestNow(Chronos::parse('2026-05-25 12:47:05'));

        try {
            $sourceDto = $this->createSource();
            $sourceId  = $sourceDto->id;
            $sourceUrl = $sourceDto->url;
            $payload   = $this->fixtureJson('github', 'push', [
                'repository' => [
                    'html_url' => $sourceUrl,
                ],
            ]);

            $firstResponse = $this->request(
                'POST',
                '/webhooks/github/' . $sourceId,
                $payload,
                $this->githubHeaders(
                    $payload,
                    (string) $sourceDto->providerConfig['webhook_secret'],
                    'push',
                ),
                true,
            );
            $firstResponse->assertStatus(202);

            $jobs = $this->sourceSyncJobs($sourceDto->organizationId);
            self::assertCount(1, $jobs);
            $firstJob = $jobs[0];

            /** @var JobRepositoryInterface $jobRepository */
            $jobRepository = $this->getService(JobRepositoryInterface::class);
            $firstJob->markCompleted();
            $jobRepository->save($firstJob);

            $secondResponse = $this->request(
                'POST',
                '/webhooks/github/' . $sourceId,
                $payload,
                $this->githubHeaders(
                    $payload,
                    (string) $sourceDto->providerConfig['webhook_secret'],
                    'create',
                ),
                true,
            );
            $secondResponse->assertStatus(202);
        } finally {
            Chronos::setTestNow();
        }

        $jobs = $this->sourceSyncJobs($sourceDto->organizationId);
        self::assertCount(2, $jobs);

        $jobIds = array_map(
            static fn (Job $job): string => $job->getIdentifier()->toString(),
            $jobs,
        );

        /** @var WebhookApiFacadeInterface $webhookApi */
        $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
        $events     = $webhookApi->recentEvents($sourceId, 2);
        self::assertCount(2, $events);

        $eventJobIds = [];
        foreach ($events as $event) {
            self::assertSame('processed', $event->status);
            self::assertNotNull($event->scheduledJobId);
            $eventJobIds[] = $event->scheduledJobId;
        }

        self::assertCount(2, array_unique($eventJobIds));
        foreach ($eventJobIds as $eventJobId) {
            self::assertContains($eventJobId, $jobIds);
        }
    }

    #[Test]
    public function itRejectsWebhookWhenGithubSignatureDoesNotMatch(): void
    {
        $sourceDto = $this->createSource();
        $sourceId  = $sourceDto->id;
        $this->fixtureJson('github', 'push', [
            'repository' => [
                'html_url' => $sourceDto->url,
            ],
        ]);

        $testResponse = $this->postJson(
            '/webhooks/github/' . $sourceId,
            $this->loadWebhookPayload('github', 'push', [
                'repository' => [
                    'html_url' => $sourceDto->url,
                ],
            ]),
            [
                'X-GitHub-Event'      => 'push',
                'X-GitHub-Delivery'   => $this->webhookDeliveryId(),
                'X-Hub-Signature-256' => 'sha256=does-not-match',
            ],
        );

        $testResponse->assertStatus(401);
        self::assertSame('Invalid GitHub signature.', $testResponse->json()['message'] ?? null);

        /** @var WebhookApiFacadeInterface $webhookApi */
        $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
        $events     = $webhookApi->recentEvents($sourceId, 1);
        self::assertSame([], $events);
    }

    #[Test]
    public function itRejectsMalformedPayloadAsInvalidWebhook(): void
    {
        $sourceDto   = $this->createSource();
        $sourceId    = $sourceDto->id;
        $payload     = json_encode('invalid-json', JSON_THROW_ON_ERROR);
        $payloadHash = hash_hmac('sha256', $payload, (string) $sourceDto->providerConfig['webhook_secret']);

        $testResponse = $this->request(
            'POST',
            '/webhooks/github/' . $sourceId,
            $payload,
            [
                'Content-Type'        => 'application/json',
                'X-GitHub-Event'      => 'push',
                'X-GitHub-Delivery'   => bin2hex(random_bytes(8)),
                'X-Hub-Signature-256' => 'sha256=' . $payloadHash,
            ],
            true,
        );

        $testResponse->assertStatus(400);
        self::assertSame('Invalid webhook payload.', $testResponse->json()['message'] ?? null);

        /** @var WebhookApiFacadeInterface $webhookApi */
        $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
        $events     = $webhookApi->recentEvents($sourceId, 1);
        self::assertNotEmpty($events);
        self::assertSame('rejected', $events[0]->status);
    }

    #[Test]
    public function itReturnsTheSameResultForDuplicateDelivery(): void
    {
        $sourceDto = $this->createSource();
        $sourceId  = $sourceDto->id;
        $sourceUrl = $sourceDto->url;
        $delivery  = bin2hex(random_bytes(8));
        $payload   = $this->loadWebhookPayload('github', 'push', [
            'repository' => [
                'html_url' => $sourceUrl,
            ],
        ]);
        $payloadString = $this->fixtureJson('github', 'push', [
            'repository' => [
                'html_url' => $sourceUrl,
            ],
        ]);
        $signature = $this->githubWebhookSignature(
            $payloadString,
            (string) $sourceDto->providerConfig['webhook_secret'],
        );

        $testResponse = $this->postJson(
            '/webhooks/github/' . $sourceId,
            $payload,
            [
                'X-GitHub-Event'      => 'push',
                'X-GitHub-Delivery'   => $delivery,
                'X-Hub-Signature-256' => $signature,
            ],
        );
        $testResponse->assertStatus(202);

        /** @var WebhookApiFacadeInterface $webhookApi */
        $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
        $events     = $webhookApi->recentEvents($sourceId, 1);
        self::assertNotEmpty($events);
        $initialStatus = $events[0]->status;
        $initialJobId  = $events[0]->scheduledJobId;
        self::assertNotNull($initialJobId);
        self::assertCount(1, $this->sourceSyncJobs($sourceDto->organizationId));

        $second = $this->postJson(
            '/webhooks/github/' . $sourceId,
            $payload,
            [
                'X-GitHub-Event'      => 'push',
                'X-GitHub-Delivery'   => $delivery,
                'X-Hub-Signature-256' => $signature,
            ],
        );
        $second->assertStatus(202);

        self::assertSame($testResponse->json()['message'] ?? null, $second->json()['message'] ?? null);

        $events = $webhookApi->recentEvents($sourceId, 1);
        self::assertNotEmpty($events);
        self::assertSame($initialStatus, $events[0]->status);
        self::assertSame($initialJobId, $events[0]->scheduledJobId);
        self::assertCount(1, $this->sourceSyncJobs($sourceDto->organizationId));
    }

    #[Test]
    public function itProcessesGitlabPushWebhookWithValidToken(): void
    {
        $sourceDto = $this->createSource(
            provider: 'gitlab',
            url: 'https://gitlab.com/org/' . bin2hex(random_bytes(6)),
        );
        $sourceId  = $sourceDto->id;
        $sourceUrl = $sourceDto->url;
        $payload   = $this->fixtureJson('gitlab', 'push', [
            'project' => [
                'web_url' => $sourceUrl,
            ],
        ]);

        $testResponse = $this->request(
            'POST',
            '/webhooks/gitlab/' . $sourceId,
            $payload,
            $this->gitlabHeaders(
                (string) $sourceDto->providerConfig['webhook_secret'],
                'Push Hook',
            ),
            true,
        );

        $testResponse->assertStatus(202);
        self::assertSame('Sync scheduled for ' . $sourceUrl . '.', $testResponse->json()['message'] ?? null);

        /** @var WebhookApiFacadeInterface $webhookApi */
        $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
        $events     = $webhookApi->recentEvents($sourceId, 1);
        self::assertNotEmpty($events);
        self::assertSame('processed', $events[0]->status);
        self::assertNotNull($events[0]->scheduledJobId);
        self::assertCount(1, $this->sourceSyncJobs($sourceDto->organizationId));
    }

    #[Test]
    public function itIgnoresUnsupportedGitlabTagPushEvent(): void
    {
        $sourceDto = $this->createSource(
            provider: 'gitlab',
            url: 'https://gitlab.com/org/' . bin2hex(random_bytes(6)),
        );
        $sourceId  = $sourceDto->id;
        $sourceUrl = $sourceDto->url;
        $payload   = $this->fixtureJson('gitlab', 'tag_push', [
            'project' => [
                'web_url' => $sourceUrl,
            ],
        ]);

        $testResponse = $this->request(
            'POST',
            '/webhooks/gitlab/' . $sourceId,
            $payload,
            $this->gitlabHeaders(
                (string) $sourceDto->providerConfig['webhook_secret'],
                'Tag Push Hook',
            ),
            true,
        );

        $testResponse->assertStatus(202);
        self::assertSame('GitLab event ignored.', $testResponse->json()['message'] ?? null);
    }

    #[Test]
    public function itSchedulesSourceSyncForValidBitbucketPushWebhook(): void
    {
        $sourceDto = $this->createSource(
            provider: 'bitbucket',
            url: 'https://bitbucket.org/workspace/' . bin2hex(random_bytes(6)),
        );
        $sourceId  = $sourceDto->id;
        $sourceUrl = $sourceDto->url;
        $payload   = $this->fixtureJson('bitbucket', 'push', [
            'repository' => [
                'links' => [
                    'html' => [
                        'href' => $sourceUrl,
                    ],
                ],
            ],
        ]);

        $testResponse = $this->request(
            'POST',
            '/webhooks/bitbucket/' . $sourceId,
            $payload,
            $this->bitbucketHeaders(
                $payload,
                (string) $sourceDto->providerConfig['webhook_secret'],
                'repo:push',
            ),
            true,
        );

        $testResponse->assertStatus(202);
        self::assertSame('Sync scheduled for ' . $sourceUrl . '.', $testResponse->json()['message'] ?? null);

        /** @var WebhookApiFacadeInterface $webhookApi */
        $webhookApi = $this->getService(WebhookApiFacadeInterface::class);
        $events     = $webhookApi->recentEvents($sourceId, 1);
        self::assertNotEmpty($events);
        self::assertSame('processed', $events[0]->status);
        self::assertNotNull($events[0]->scheduledJobId);
        self::assertCount(1, $this->sourceSyncJobs($sourceDto->organizationId));
    }

    #[Test]
    public function invalidSignatureCannotReserveDeliveryId(): void
    {
        $sourceDto  = $this->createSource();
        $payload    = $this->fixtureJson('github', 'ping', [
            'repository' => [
                'html_url' => $sourceDto->url,
            ],
        ]);
        $headers = $this->githubHeaders($payload, (string) $sourceDto->providerConfig['webhook_secret'], 'ping');
        $invalid = [
            ...$headers,
            'X-Hub-Signature-256' => 'sha256=invalid',
        ];
        $this->request('POST', '/webhooks/github/' . $sourceDto->id, $payload, $invalid, true)->assertStatus(401);
        $this->request('POST', '/webhooks/github/' . $sourceDto->id, $payload, $headers, true)->assertStatus(202);
        $webhooks = $this->getService(WebhookApiFacadeInterface::class);
        self::assertCount(1, $webhooks->recentEvents($sourceDto->id));
    }

    #[Test]
    public function providerMustMatchSourceEvenWithValidSignature(): void
    {
        $sourceDto  = $this->createSource();
        $payload    = $this->fixtureJson('github', 'ping', [
            'repository' => [
                'html_url' => $sourceDto->url,
            ],
        ]);
        $this->request('POST', '/webhooks/gitlab/' . $sourceDto->id, $payload, $this->githubHeaders($payload, (string) $sourceDto->providerConfig['webhook_secret'], 'ping'), true)->assertStatus(404);
        $webhooks = $this->getService(WebhookApiFacadeInterface::class);
        self::assertSame([], $webhooks->recentEvents($sourceDto->id));
    }

    #[Test]
    public function itAnswersConflictForAnotherRepositoryAndRepeatsItForTheSameDelivery(): void
    {
        $sourceDto = $this->createSource();
        $payload   = $this->fixtureJson('github', 'push', [
            'repository' => [
                'html_url' => 'https://github.com/org/another-repository',
            ],
        ]);
        $headers = $this->githubHeaders($payload, (string) $sourceDto->providerConfig['webhook_secret'], 'push');

        // The second answer is rebuilt from the stored event.
        foreach ([1, 2] as $attempt) {
            $testResponse = $this->request('POST', '/webhooks/github/' . $sourceDto->id, $payload, $headers, true);
            $testResponse->assertStatus(409);
            self::assertSame('Webhook repository does not match the configured source.', $testResponse->json()['message'] ?? null, 'attempt ' . $attempt);
        }
    }

    #[Test]
    public function itAnswersNotFoundForAnUnknownProvider(): void
    {
        $sourceDto = $this->createSource();

        $testResponse = $this->request('POST', '/webhooks/no_such_provider/' . $sourceDto->id, '{}', [], true);

        $testResponse->assertStatus(404);
        self::assertSame('Source not found.', $testResponse->json()['message'] ?? null);
    }

    private function createSource(string $provider = 'github', ?string $url = null): SourceDto
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->createOrganization($userDto);

        /** @var SourceApiFacadeInterface $sources */
        $sources   = $this->getService(SourceApiFacadeInterface::class);
        $sourceUrl = $url ?? ('https://github.com/org/' . bin2hex(random_bytes(6)));

        return $sources->registerSource(new SourceRegistrationRequestDto(
            $provider,
            $sourceUrl,
            $organizationDto->id,
        ));
    }

    private function createVerifiedUser(): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            'webhook-controller-' . bin2hex(random_bytes(6)) . '@example.test',
            'Webhook User',
            self::PASSWORD,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createOrganization(UserDto $userDto): OrganizationDto
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return $organizations->registerOrganization('webhook-org-' . bin2hex(random_bytes(6)), $userDto->id);
    }

    /**
     * @return list<Job>
     */
    private function sourceSyncJobs(string $organizationId): array
    {
        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);

        return $jobs->findByOrganization($organizationId, [
            'type'  => 'source.sync',
            'limit' => 20,
        ]);
    }
}
