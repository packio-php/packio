<?php

declare(strict_types=1);

namespace PackioTest\Integration\Organization\Infrastructure\Persistence\Cycle;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\Capsule;
use Migration\BackfillOrganizationDeletionRunIds;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Worker\Application\JobProcessor;
use Packio\Worker\Domain\Entity\Job;
use Packio\Worker\Domain\Repository\JobRepositoryInterface;
use PackioTest\Support\Base\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function array_filter;
use function array_first;
use function bin2hex;
use function random_bytes;

require_once __DIR__ . '/../../../../../../data/db/cycle/migrations/20260823.232001_0_0_default_backfill_organization_deletion_run_ids.php';

final class OrganizationDeletionRunMigrationTest extends IntegrationTestCase
{
    #[Test]
    public function itUsesTheLegacyJobGenerationAndTheMigratedJobCompletesDeletion(): void
    {
        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations   = $this->getService(OrganizationApiFacadeInterface::class);
        $organizationDto = $organizations->registerOrganization(
            'legacy-organization-delete-' . bin2hex(random_bytes(6)),
        );
        $legacyRunId = Uuid::uuid7()->toString();

        $databaseProvider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $databaseProvider);
        $database = $databaseProvider->database();
        $database->update('organizations')->values([
            'active'          => false,
            'status'          => 'deleting',
            'deletion_run_id' => null,
        ])->where('uuid', $organizationDto->id)->run();

        /** @var JobRepositoryInterface $jobs */
        $jobs      = $this->getService(JobRepositoryInterface::class);
        $legacyJob = Job::schedule(
            organizationId: $organizationDto->id,
            type: 'organization.delete',
            payload: [
                'organization_id'      => $organizationDto->id,
                'requested_by_user_id' => 'legacy-user',
                'deletion_run_id'      => $legacyRunId,
                'stage'                => 'schedule_cleanup',
            ],
            priority: 20,
            idempotencyKey: 'organization-delete:' . $organizationDto->id,
        );
        $jobs->save($legacyJob);

        new BackfillOrganizationDeletionRunIds()
            ->withCapsule(new Capsule($database))
            ->up()
        ;

        self::assertSame(
            $legacyRunId,
            $database->query(
                'SELECT deletion_run_id FROM organizations WHERE uuid = ?',
                [$organizationDto->id],
            )->fetchColumn(),
        );

        $this->rebuildContainer();

        /** @var JobProcessor $jobProcessor */
        $jobProcessor = $this->getService(JobProcessor::class);

        /** @var JobRepositoryInterface $jobs */
        $jobs               = $this->getService(JobRepositoryInterface::class);
        $persistedLegacyJob = $jobs->findById($legacyJob->getIdentifier()->toString());
        self::assertInstanceOf(Job::class, $persistedLegacyJob);
        $jobProcessor->process($persistedLegacyJob);

        $finalizeJobs = array_filter(
            $jobs->findByOrganization($organizationDto->id, [
                'type' => 'organization.delete',
            ]),
            static fn (Job $job): bool => 'finalize' === ($job->payload()['stage'] ?? null),
        );
        self::assertCount(1, $finalizeJobs);
        $finalizeJob = array_first($finalizeJobs);
        self::assertInstanceOf(Job::class, $finalizeJob);
        $jobProcessor->process($finalizeJob);

        /** @var OrganizationApiFacadeInterface $organizations */
        $organizations = $this->getService(OrganizationApiFacadeInterface::class);
        self::assertNull($organizations->findOrganization($organizationDto->id));
    }
}
