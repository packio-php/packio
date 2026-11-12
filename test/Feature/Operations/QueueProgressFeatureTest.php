<?php

declare(strict_types=1);

namespace PackioTest\Feature\Operations;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Worker\Api\JobApiFacadeInterface;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

use function bin2hex;
use function random_bytes;
use function ucfirst;

final class QueueProgressFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itExposesAdminQueueAndSystemAuthMailProgressWithoutChangingStatusCounts(): void
    {
        $userDto          = $this->createUser('queue-admin', ['admin']);
        $organizationDto  = $this->createOrganization($userDto);
        $foreign          = $this->createOrganization($userDto);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);

        $ready = Job::schedule($organizationDto->id, 'queue.ready', [], scheduledAt: new Chronos('-2 minutes'));
        $retry = Job::schedule($organizationDto->id, 'queue.retry', [], scheduledAt: new Chronos('+1 minute'));
        $retry->markRunning('worker-retry');
        $retry->markPendingForRetry();

        $running = Job::schedule($organizationDto->id, 'queue.running', []);
        $running->markRunning('worker-current');

        $stuck = Job::schedule($organizationDto->id, 'queue.stuck', []);
        $stuck->markRunning('worker-stale');

        $failed = Job::schedule($organizationDto->id, 'queue.failed', []);
        $failed->markRunning('worker-failed');
        $failed->markFailed('failed');

        $otherOrganizationJob = Job::schedule(
            $foreign->id,
            'queue.other-organization',
            [],
            scheduledAt: new Chronos('-5 minutes'),
        );
        $authMail = Job::schedule(
            'system:identity',
            'identity.auth_mail',
            [],
            scheduledAt: new Chronos('-10 minutes'),
        );

        foreach ([$ready, $retry, $running, $stuck, $failed, $otherOrganizationJob, $authMail] as $job) {
            $jobs->save($job);
        }

        /** @var DatabaseInterface $database */
        $database = $this->getService('dbal')->database();
        $affected = $database->update('jobs')->values([
            'started_at' => new Chronos('-31 minutes')->format('Y-m-d H:i:s'),
        ])->where('uuid', $stuck->getIdentifier()->toString())->run();
        self::assertSame(1, $affected);

        $this->logIn($userDto->email);
        $testResponse = $this->get('/admin/jobs', [
            'Accept'    => 'application/json',
            'X-Inertia' => 'true',
        ]);

        $testResponse->assertStatus(200)->assertInertiaComponent('Admin/Jobs');
        $props = $testResponse->json()['props'];

        self::assertArrayHasKey('queue_progress', $props);
        self::assertArrayHasKey('auth_mail_progress', $props);
        $queueProgress = $props['queue_progress'] ?? null;
        self::assertSame(4, $queueProgress['pending'] ?? null);
        self::assertSame(2, $queueProgress['running'] ?? null);
        self::assertSame(1, $queueProgress['failed'] ?? null);
        self::assertSame(1, $queueProgress['retrying'] ?? null);
        self::assertSame(1, $queueProgress['stuck'] ?? null);
        self::assertGreaterThanOrEqual(590, $queueProgress['oldest_ready_age_seconds'] ?? null);

        $authMailProgress = $props['auth_mail_progress'] ?? null;
        self::assertSame(1, $authMailProgress['pending'] ?? null);
        self::assertSame(0, $authMailProgress['running'] ?? null);
        self::assertSame(0, $authMailProgress['failed'] ?? null);
        self::assertSame(0, $authMailProgress['retrying'] ?? null);
        self::assertSame(0, $authMailProgress['stuck'] ?? null);
        self::assertGreaterThanOrEqual(590, $authMailProgress['oldest_ready_age_seconds'] ?? null);
        self::assertSame(1, $props['status_counts']['failed'] ?? null);
    }

    #[Test]
    public function queueProgressIsIdleAndOrganizationScoped(): void
    {
        $userDto         = $this->createUser('queue-scope');
        $organizationDto = $this->createOrganization($userDto);
        $foreign         = $this->createOrganization($userDto);

        /** @var JobRepositoryInterface $jobs */
        $jobs = $this->getService(JobRepositoryInterface::class);
        $jobs->save(Job::schedule($organizationDto->id, 'queue.ready', [], scheduledAt: new Chronos('-3 minutes')));
        $jobs->save(Job::schedule($foreign->id, 'queue.foreign', [], scheduledAt: new Chronos('-20 minutes')));
        $jobs->save(Job::schedule('system:identity', 'identity.auth_mail', [], scheduledAt: new Chronos('-30 minutes')));

        /** @var JobApiFacadeInterface $jobApi */
        $jobApi = $this->getService(JobApiFacadeInterface::class);

        self::assertSame([
            'pending'                  => 0,
            'running'                  => 0,
            'failed'                   => 0,
            'retrying'                 => 0,
            'stuck'                    => 0,
            'oldest_ready_age_seconds' => null,
        ], $jobApi->queueProgress('missing-organization'));

        $scoped = $jobApi->queueProgress($organizationDto->id);
        self::assertSame(1, $scoped['pending']);
        self::assertGreaterThanOrEqual(170, $scoped['oldest_ready_age_seconds']);
        self::assertLessThan(240, $scoped['oldest_ready_age_seconds']);

        $this->logIn($userDto->email);
        $this->get('/admin/jobs')->assertStatus(403);
    }

    /** @param list<string> $roles */
    private function createUser(string $prefix, array $roles = ['user']): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity    = $this->getService(IdentityApiFacadeInterface::class);
        $userDto     = $identity->createUser(
            $prefix . '-' . bin2hex(random_bytes(6)) . '@example.test',
            ucfirst($prefix) . ' User',
            self::PASSWORD,
            $roles,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createOrganization(UserDto $userDto): OrganizationDto
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);

        return $organizations->registerOrganization('queue-' . bin2hex(random_bytes(6)), $userDto->id);
    }

    private function logIn(string $email): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');
    }
}
