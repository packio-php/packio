<?php

declare(strict_types=1);

namespace PackioTest\Feature\Worker;

use Cake\Chronos\Chronos;
use Cycle\Database\DatabaseProviderInterface;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\UserDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\Dto\SourceRegistrationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Domain\Entity\Source;
use Packio\Source\Domain\Repository\SourceRepositoryInterface;
use Packio\Worker\Api\JobApiFacadeInterface;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Entity\JobLog;
use Packio\Worker\Domain\Repository\JobLogRepositoryInterface;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function bin2hex;
use function random_bytes;
use function ucfirst;

final class WorkerFeatureTest extends FeatureTestCase
{
    private const string PASSWORD = 'Password1!';

    #[Test]
    public function itMapsJobLogsToApiDtos(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();

        /** @var JobApiFacadeInterface $jobs */
        $jobs = $this->getService(JobApiFacadeInterface::class);

        /** @var JobLogRepositoryInterface $jobLogRepository */
        $jobLogRepository = $this->getService(JobLogRepositoryInterface::class);

        $jobDto = $jobs->enqueue($organization->id, 'test.log', [
            'purpose' => 'dto-mapping',
        ]);
        $jobLog = JobLog::record(
            $jobDto->id,
            JobLog::LEVEL_WARNING,
            'A mapped warning.',
            [
                'attempt' => 2,
            ],
        );
        $jobLogRepository->save($jobLog);

        $logs = $jobs->findLogsByJob($jobDto->id);

        self::assertCount(1, $logs);
        self::assertSame($jobLog->getIdentifier()->toString(), $logs[0]->id);
        self::assertSame($jobDto->id, $logs[0]->jobId);
        self::assertSame(JobLog::LEVEL_WARNING, $logs[0]->level);
        self::assertSame('A mapped warning.', $logs[0]->message);
        self::assertSame([
            'attempt' => 2,
        ], $logs[0]->context);
        self::assertSame($jobLog->getCreatedAt()->format(DATE_ATOM), $logs[0]->createdAt);
    }

    #[Test]
    public function facadeCoalescesSourceSyncWithPendingJob(): void
    {
        [, $organization] = $this->createOrganizationWithOwner();
        $sourceId         = Uuid::uuid7()->toString();

        /** @var JobApiFacadeInterface $jobs */
        $jobs    = $this->getService(JobApiFacadeInterface::class);
        $payload = [
            'source_id' => $sourceId,
        ];

        $jobDto  = $jobs->enqueueSourceSyncOnce($organization->id, $sourceId, 'sync-a-' . $sourceId, $payload);
        $second  = $jobs->enqueueSourceSyncOnce($organization->id, $sourceId, 'sync-b-' . $sourceId, $payload);

        self::assertSame($jobDto->id, $second->id);
        self::assertSame(Job::STATUS_PENDING, $second->status);
    }

    #[Test]
    public function facadeReportsSourceCreationOnlyOnce(): void
    {
        [, $organization] = $this->createOrganizationWithOwner();
        $key              = 'source-create-' . bin2hex(random_bytes(6));

        /** @var JobApiFacadeInterface $jobs */
        $jobs = $this->getService(JobApiFacadeInterface::class);

        self::assertTrue($jobs->enqueueSourceCreation($organization->id, $key, [
            'purpose' => 'feature',
        ]));
        self::assertFalse($jobs->enqueueSourceCreation($organization->id, $key, [
            'purpose' => 'feature',
        ]));
    }

    #[Test]
    public function itKeepsOldSourceDeleteJobWhenDeletingSourceStillExists(): void
    {
        [, $organization] = $this->createOrganizationWithOwner();
        $sourceDto        = $this->createSource($organization);

        /** @var SourceRepositoryInterface $sourceRepository */
        $sourceRepository = $this->getService(SourceRepositoryInterface::class);
        $source           = $sourceRepository->findByIdInOrganization($sourceDto->id, $organization->id);
        self::assertInstanceOf(Source::class, $source);
        $source->markDeleting();
        $sourceRepository->save($source);

        /** @var JobRepositoryInterface $jobRepository */
        $jobRepository   = $this->getService(JobRepositoryInterface::class);
        $missingSourceId = Uuid::uuid7()->toString();
        $protectedJob    = Job::schedule(
            organizationId: $organization->id,
            type: 'source.delete',
            payload: [
                'organization_id' => $organization->id,
            ],
            idempotencyKey: 'source-delete:' . $sourceDto->id,
        );
        $protectedJob->markFailed('Source deletion failed.');
        $jobRepository->save($protectedJob);

        $staleDeleteJob = Job::schedule(
            organizationId: $organization->id,
            type: 'source.delete',
            payload: [
                'source_id' => $missingSourceId,
            ],
            idempotencyKey: 'source-delete:' . $missingSourceId,
        );
        $staleDeleteJob->markFailed('Source deletion failed.');
        $jobRepository->save($staleDeleteJob);
        $this->setJobCreatedAt($protectedJob, Chronos::parse('2026-01-01T00:00:00+00:00'));
        $this->setJobCreatedAt($staleDeleteJob, Chronos::parse('2026-01-01T00:00:00+00:00'));

        self::assertSame(1, $jobRepository->deleteOlderThan(Chronos::parse('2026-02-01T00:00:00+00:00')));
        self::assertInstanceOf(Job::class, $jobRepository->findById($protectedJob->getIdentifier()->toString()));
        self::assertNull($jobRepository->findById($staleDeleteJob->getIdentifier()->toString()));
    }

    #[Test]
    public function itCompletesStaleSourceDeletionGenerationsWithoutDeletingTheSource(): void
    {
        [, $organization] = $this->createOrganizationWithOwner();
        $sourceDto        = $this->createSource($organization);

        /** @var JobRepositoryInterface $jobRepository */
        $jobRepository  = $this->getService(JobRepositoryInterface::class);
        $staleJob       = Job::schedule(
            organizationId: $organization->id,
            type: 'source.delete',
            payload: [
                'source_id'       => $sourceDto->id,
                'organization_id' => $organization->id,
                'deletion_run_id' => Uuid::uuid7()->toString(),
            ],
        );
        $jobRepository->save($staleJob);

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($staleJob);

        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);
        self::assertSame('active', $sources->findSource($sourceDto->id)?->status);
        self::assertSame(Job::STATUS_COMPLETED, $staleJob->status());
    }

    #[Test]
    public function itCompletesStaleOrganizationDeletionGenerationsWithoutDeletingMembers(): void
    {
        [$owner, $member, $organization] = $this->createOrganizationWithOwnerAndMember();

        /** @var JobRepositoryInterface $jobRepository */
        $jobRepository  = $this->getService(JobRepositoryInterface::class);
        $staleJob       = Job::schedule(
            organizationId: $organization->id,
            type: 'organization.delete',
            payload: [
                'organization_id' => $organization->id,
                'deletion_run_id' => Uuid::uuid7()->toString(),
                'stage'           => 'schedule_cleanup',
            ],
        );
        $jobRepository->save($staleJob);

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);
        $jobProcessor->process($staleJob);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertSame('active', $organizations->findOrganization($organization->id)?->status);
        self::assertSame(Job::STATUS_COMPLETED, $staleJob->status());

        // Both memberships remain: no cleanup side effect ran for stale work.
        $this->logIn($member->email);
        $this->get('/organization/' . $organization->slug . '/members')->assertStatus(200);
    }

    #[Test]
    public function itReturnsNotFoundForFormerUserJobsWebRoutesEvenForAdmins(): void
    {
        [$owner, $organization] = $this->createOrganizationWithOwner();
        $jobDto                 = $this->createPendingJob($organization);
        $jobId                  = $jobDto->getIdentifier()->toString();

        $this->logIn($owner->email);
        $this->get('/organization/' . $organization->slug . '/jobs')->assertStatus(404);
        $this->get('/organization/' . $organization->slug . '/jobs/' . $jobId)->assertStatus(404);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/jobs/' . $jobId . '/cancel',
        )->assertStatus(404);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/jobs/' . $jobId . '/retry',
        )->assertStatus(404);

        $userDto = $this->adminifiedOwner($owner);
        self::assertInstanceOf(UserDto::class, $userDto);

        $this->logIn($userDto->email);
        $this->get('/organization/' . $organization->slug . '/jobs')->assertStatus(404);
        $this->get('/organization/' . $organization->slug . '/jobs/' . $jobId)->assertStatus(404);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/jobs/' . $jobId . '/cancel',
        )->assertStatus(404);
        $this->withCsrf()->post(
            '/organization/' . $organization->slug . '/jobs/' . $jobId . '/retry',
        )->assertStatus(404);
    }

    private function adminifiedOwner(UserDto $userDto): UserDto
    {
        /** @var IdentityApiFacadeInterface $identity */
        $identity = $this->getService(IdentityApiFacadeInterface::class);
        $adminDto = $identity->updateUser(
            $userDto->id,
            $userDto->email,
            $userDto->displayName,
            ['user', 'admin'],
        );
        self::assertInstanceOf(UserDto::class, $adminDto);

        return $adminDto;
    }

    /**
     * @return array{0: UserDto, 1: OrganizationDto}
     */
    private function createOrganizationWithOwner(): array
    {
        $userDto = $this->createVerifiedUser('owner');

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations   = $this->getService(OrganizationApiFacadeInterface::class);
        $organizationDto = $organizations->registerOrganization(
            'worker-org-owner-' . bin2hex(random_bytes(5)),
            $userDto->id,
        );

        return [$userDto, $organizationDto];
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
            $prefix . '-' . bin2hex(random_bytes(6)) . '@example.test',
            ucfirst($prefix) . ' User',
            self::PASSWORD,
        );
        $identity->markUserEmailAsVerified($userDto->id);

        return $userDto;
    }

    private function createSource(OrganizationDto $organizationDto): SourceDto
    {
        /** @var SourceApiFacadeInterface $sources */
        $sources = $this->getService(SourceApiFacadeInterface::class);

        return $sources->registerSource(new SourceRegistrationRequestDto(
            'github',
            'https://github.com/org/' . bin2hex(random_bytes(6)),
            $organizationDto->id,
        ));
    }

    private function createPendingJob(OrganizationDto $organizationDto): Job
    {
        /** @var JobApiFacadeInterface $jobs */
        $jobs   = $this->getService(JobApiFacadeInterface::class);
        $jobDto = $jobs->enqueue($organizationDto->id, 'source.sync', [
            'purpose' => 'feature',
        ]);

        /** @var JobRepositoryInterface $jobRepository */
        $jobRepository = $this->getService(JobRepositoryInterface::class);
        $reloaded      = $jobRepository->findById($jobDto->id);

        self::assertInstanceOf(Job::class, $reloaded);

        return $reloaded;
    }

    private function setJobCreatedAt(Job $job, Chronos $createdAt): void
    {
        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);

        $affectedRows = $databaseProvider
            ->database()
            ->update('jobs')
            ->values([
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
            ])
            ->where('uuid', $job->getIdentifier()->toString())
            ->run()
        ;

        self::assertSame(1, $affectedRows);
    }

    private function logIn(string $email): void
    {
        $this->withCsrf()->post('/auth/login', [
            'email'    => $email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/organizations');
    }
}
