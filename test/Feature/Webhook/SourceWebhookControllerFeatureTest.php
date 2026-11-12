<?php

declare(strict_types=1);

namespace PackioTest\Feature\Webhook;

use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Shared\Domain\Organization\ActorContext;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Webhook\Api\WebhookApiFacadeInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function hash_hmac;
use function random_bytes;
use function ucfirst;

final class SourceWebhookControllerFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itReprocessesRejectedWebhookDeliveryForOwner(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->createOrganization($userDto);
        $sourceDto       = $this->createSource($organizationDto);
        $deliveryId      = bin2hex(random_bytes(8));

        $this->createRejectedWebhookDelivery($sourceDto, $deliveryId);
        $this->logIn($userDto->email);

        $testResponse = $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/sources/webhooks/' . $sourceDto->id . '/' . $deliveryId . '/reprocess',
        );

        $testResponse->assertRedirect('/organization/' . $organizationDto->slug . '/sources/' . $sourceDto->id);
        $this->assertWebhookStatus($sourceDto->id, 'processed');
        $this->assertSourceSyncJobs($organizationDto->id, 1);

        $sourcePageResponse = $this->get('/organization/' . $organizationDto->slug . '/sources', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);
        $sourcePageResponse->assertStatus(200)->assertInertiaComponent('Sources/Index');
        self::assertSame('success', $sourcePageResponse->json()['flash']['toasts'][0]['type'] ?? null);
        self::assertSame(
            'Webhook delivery reprocessing queued.',
            $sourcePageResponse->json()['flash']['toasts'][0]['message'] ?? null,
        );
    }

    #[Test]
    public function itRejectsMemberReprocessingRejectedWebhookDelivery(): void
    {
        $userDto         = $this->createVerifiedUser('owner');
        $member          = $this->createVerifiedUser('member');
        $organizationDto = $this->createOrganization($userDto);
        $this->addMember($organizationDto, $member);

        $sourceDto  = $this->createSource($organizationDto);
        $deliveryId = 'member-' . bin2hex(random_bytes(8));
        $this->createRejectedWebhookDelivery($sourceDto, $deliveryId);

        $this->logIn($member->email);
        $testResponse = $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/sources/webhooks/' . $sourceDto->id . '/' . $deliveryId . '/reprocess',
        );

        $testResponse->assertRedirect('/organization/' . $organizationDto->slug);
        $this->assertWebhookStatus($sourceDto->id, 'rejected');
    }

    #[Test]
    public function itRejectsReprocessingWebhookDeliveryFromAnotherOrganization(): void
    {
        $userDto               = $this->createVerifiedUser();
        $organizationDto       = $this->createOrganization($userDto, 'webhook-source-primary-' . bin2hex(random_bytes(6)));
        $secondaryOrganization = $this->createOrganization($userDto, 'webhook-source-secondary-' . bin2hex(random_bytes(6)));

        $sourceDto  = $this->createSource($secondaryOrganization);
        $deliveryId = 'foreign-' . bin2hex(random_bytes(8));
        $this->createRejectedWebhookDelivery($sourceDto, $deliveryId);

        $this->logIn($userDto->email);
        $testResponse = $this->withCsrf()->post(
            '/organization/' . $organizationDto->slug . '/sources/webhooks/' . $sourceDto->id . '/' . $deliveryId . '/reprocess',
        );

        $testResponse->assertRedirect('/organization/' . $organizationDto->slug . '/sources');
        $this->assertWebhookStatus($sourceDto->id, 'rejected');
    }

    #[Test]
    public function itReturnsNotFoundWhenReprocessingDeliveryOfInactiveSource(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->createOrganization($userDto);
        $sourceDto       = $this->createSource($organizationDto);
        $deliveryId      = bin2hex(random_bytes(8));

        $this->createRejectedWebhookDelivery($sourceDto, $deliveryId);

        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);
        $sourceApi->updateSettingsInContext(
            new ActorContext($organizationDto->id, $userDto->id, 'owner'),
            $sourceDto->id,
            $sourceDto->visibility,
            false,
            $sourceDto->keepLastReleases,
        );

        $this->assertReprocessNotFound($sourceDto->id, $deliveryId);
    }

    #[Test]
    public function itReturnsNotFoundWhenReprocessingUnknownDelivery(): void
    {
        $userDto         = $this->createVerifiedUser();
        $organizationDto = $this->createOrganization($userDto);
        $sourceDto       = $this->createSource($organizationDto);
        $deliveryId      = bin2hex(random_bytes(8));

        $this->createRejectedWebhookDelivery($sourceDto, $deliveryId);

        $this->assertReprocessNotFound($sourceDto->id, 'missing');
    }

    #[Test]
    public function itDeletesOnlyEventsOfTheGivenSource(): void
    {
        $organizationDto = $this->createOrganization($this->createVerifiedUser());
        $sourceA         = $this->createSource($organizationDto);
        $sourceB         = $this->createSource($organizationDto);
        foreach ([$sourceA, $sourceB] as $sourceDto) {
            $this->createRejectedWebhookDelivery($sourceDto, bin2hex(random_bytes(8)));
            $this->createRejectedWebhookDelivery($sourceDto, bin2hex(random_bytes(8)));
        }

        /** @var WebhookApiFacadeInterface $webhooks */
        $webhooks = $this->getService(WebhookApiFacadeInterface::class);

        self::assertSame(2, $webhooks->deleteEventsForSource($sourceA->id));
        self::assertSame([], $webhooks->recentEvents($sourceA->id, 10));
        self::assertCount(2, $webhooks->recentEvents($sourceB->id, 10));
        self::assertSame(0, $webhooks->deleteEventsForSource($sourceA->id));
    }

    private function assertReprocessNotFound(string $sourceId, string $deliveryId): void
    {
        /** @var WebhookApiFacadeInterface $webhooks */
        $webhooks = $this->getService(WebhookApiFacadeInterface::class);
        $before   = $webhooks->recentEvents($sourceId, 1)[0];

        $webhookResultDto = $webhooks->reprocessDelivery($sourceId, $deliveryId);

        self::assertSame(404, $webhookResultDto->statusCode);
        self::assertSame('Webhook delivery not found.', $webhookResultDto->message);
        self::assertSame($before->reprocessCount, $webhooks->recentEvents($sourceId, 1)[0]->reprocessCount);
    }

    private function createRejectedWebhookDelivery(SourceDto $sourceDto, string $deliveryId): void
    {
        /** @var WebhookApiFacadeInterface $webhooks */
        $webhooks         = $this->getService(WebhookApiFacadeInterface::class);
        $payload          = '{}';
        $webhookResultDto = $webhooks->handleWebhook(
            $sourceDto->id,
            [
                'x-github-event'      => 'push',
                'x-github-delivery'   => $deliveryId,
                'x-hub-signature-256' => 'sha256=' . hash_hmac('sha256', $payload, (string) $sourceDto->providerConfig['webhook_secret']),
            ],
            $payload,
        );
        self::assertSame(400, $webhookResultDto->statusCode);
        $event = $webhooks->recentEvents($sourceDto->id, 1)[0] ?? null;
        self::assertSame('rejected', $event->status ?? '');
    }

    private function assertWebhookStatus(string $sourceId, string $status): void
    {
        /** @var WebhookApiFacadeInterface $webhooks */
        $webhooks = $this->getService(WebhookApiFacadeInterface::class);
        $events   = $webhooks->recentEvents($sourceId, 1);
        self::assertNotEmpty($events);
        self::assertSame($status, $events[0]->status);
        if ('processed' === $status) {
            self::assertNotNull($events[0]->scheduledJobId);
        }
    }

    private function assertSourceSyncJobs(string $organizationId, int $expectedCount): void
    {
        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);

        self::assertCount(
            $expectedCount,
            $jobs->findByOrganization($organizationId, [
                'type'  => 'source.sync',
                'limit' => 20,
            ]),
        );
    }

    private function createSource(OrganizationDto $organizationDto): SourceDto
    {
        /** @var SourceApiFacadeInterface $sourceApi */
        $sourceApi = $this->getService(SourceApiFacadeInterface::class);

        return $sourceApi->registerSource(new SourceRegistrationRequestDto(
            provider: 'github',
            resource: 'https://github.com/' . $organizationDto->id . '/webhook-source-' . bin2hex(random_bytes(6)),
            organizationId: $organizationDto->id,
        ));
    }

    private function createVerifiedUser(string $prefix = 'owner'): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $userDto  = $identity->createUser(
            $prefix . '-' . bin2hex(random_bytes(6)) . '@example.test',
            ucfirst($prefix) . ' User',
            self::PASSWORD,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createOrganization(UserDto $userDto, ?string $name = null): OrganizationDto
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return $organizations->registerOrganization(
            $name ?? ('source-webhook-org-' . bin2hex(random_bytes(6))),
            $userDto->id,
        );
    }

    private function addMember(OrganizationDto $organizationDto, UserDto $userDto): void
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        $organizations->addMember($organizationDto->id, $userDto->id, 'member');
    }

    private function logIn(string $email): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');
    }
}
