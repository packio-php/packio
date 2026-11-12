<?php

declare(strict_types=1);

namespace Packio\Organization\Application\Command;

use Packio\Organization\Application\Port\IdentityServicePort;
use Packio\Organization\Application\Port\OrganizationDeletionJobSchedulerPort;
use Packio\Organization\Application\Port\OrganizationDeletionJobStatusPort;
use Packio\Organization\Application\Port\PackageDeletionSchedulerPort;
use Packio\Organization\Application\Port\SourceDeletionSchedulerPort;
use Packio\Organization\Domain\Entity\Organization;
use Packio\Organization\Domain\Repository\OrganizationInvitationRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationMemberRepositoryInterface;
use Packio\Organization\Domain\Repository\OrganizationRepositoryInterface;
use RuntimeException;

use function count;
use function in_array;
use function trim;

final readonly class OrganizationDeletionService
{
    public function __construct(
        private OrganizationRepositoryInterface $organizationRepository,
        private OrganizationInvitationRepositoryInterface $organizationInvitationRepository,
        private OrganizationMemberRepositoryInterface $organizationMemberRepository,
        private IdentityServicePort $identityServicePort,
        private SourceDeletionSchedulerPort $sourceDeletionSchedulerPort,
        private PackageDeletionSchedulerPort $packageDeletionSchedulerPort,
        private OrganizationDeletionJobSchedulerPort $organizationDeletionJobSchedulerPort,
        private OrganizationDeletionJobStatusPort $organizationDeletionJobStatusPort,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function scheduleCleanup(string $organizationId, string $deletionRunId, string $requestedByUserId = ''): array
    {
        $organizationId = $this->requireIdentifier($organizationId, 'Organization ID is required.');
        $deletionRunId  = $this->requireIdentifier($deletionRunId, 'Deletion run ID is required.');

        if (! $this->organizationRepository->isDeletionRunCurrent($organizationId, $deletionRunId)) {
            return [
                'stage'            => 'schedule_cleanup',
                'organization_id'  => $organizationId,
                'deletion_run_id'  => $deletionRunId,
                'stale_generation' => true,
            ];
        }

        $organization   = $this->organizationRepository->findById($organizationId);
        if (! $organization instanceof Organization) {
            return [
                'stage'           => 'schedule_cleanup',
                'organization_id' => $organizationId,
                'deletion_run_id' => $deletionRunId,
                'deleted'         => false,
                'already_deleted' => true,
            ];
        }

        if (! $organization->isDeleting()) {
            $organization->markDeleting();
            $this->organizationRepository->save($organization);
        }

        $invitationsDeleted = $this->deleteInvitations($organizationId);
        $membersDeleted     = $this->deleteMembers($organizationId);
        $this->identityServicePort->revokeRegistryTokensForOrganization($organizationId);

        $sources               = $this->sourceDeletionSchedulerPort->sourcesForOrganization($organizationId);
        $scheduledSourceIds    = [];
        $sourceDeleteScheduled = 0;
        $sourceDeleteSkipped   = 0;

        foreach ($sources as $source) {
            if ($this->sourceDeletionSchedulerPort->scheduleSourceDelete($source->id, $organizationId, $deletionRunId)) {
                $scheduledSourceIds[] = $source->id;
                ++$sourceDeleteScheduled;

                continue;
            }

            ++$sourceDeleteSkipped;
        }

        $packages               = $this->packageDeletionSchedulerPort->packagesForOrganization($organizationId);
        $sourceManagedPackages  = 0;
        $packageDeleteScheduled = 0;
        $packageDeleteSkipped   = 0;

        foreach ($packages as $package) {
            if ('' !== trim($package->sourceId) && in_array($package->sourceId, $scheduledSourceIds, true)) {
                ++$sourceManagedPackages;

                continue;
            }

            if ($this->packageDeletionSchedulerPort->schedulePackageDelete($organizationId, $package->name, $deletionRunId)) {
                ++$packageDeleteScheduled;

                continue;
            }

            ++$packageDeleteSkipped;
        }

        $finalizeScheduled = $this->organizationDeletionJobSchedulerPort->scheduleFinalize($organizationId, $deletionRunId);

        return [
            'stage'                    => 'schedule_cleanup',
            'organization_id'          => $organizationId,
            'requested_by_user_id'     => $requestedByUserId,
            'deletion_run_id'          => $deletionRunId,
            'invitations_deleted'      => $invitationsDeleted,
            'members_deleted'          => $membersDeleted,
            'sources_found'            => count($sources),
            'source_delete_scheduled'  => $sourceDeleteScheduled,
            'source_delete_skipped'    => $sourceDeleteSkipped,
            'packages_found'           => count($packages),
            'source_managed_packages'  => $sourceManagedPackages,
            'package_delete_scheduled' => $packageDeleteScheduled,
            'package_delete_skipped'   => $packageDeleteSkipped,
            'finalize_scheduled'       => $finalizeScheduled,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function finalize(string $organizationId, string $deletionRunId): array
    {
        $organizationId = $this->requireIdentifier($organizationId, 'Organization ID is required.');
        $deletionRunId  = $this->requireIdentifier($deletionRunId, 'Deletion run ID is required.');

        if (! $this->organizationRepository->isDeletionRunCurrent($organizationId, $deletionRunId)) {
            return [
                'stage'            => 'finalize',
                'organization_id'  => $organizationId,
                'deletion_run_id'  => $deletionRunId,
                'stale_generation' => true,
            ];
        }

        $organization   = $this->organizationRepository->findById($organizationId);

        if (! $organization instanceof Organization) {
            return [
                'stage'           => 'finalize',
                'organization_id' => $organizationId,
                'deletion_run_id' => $deletionRunId,
                'deleted'         => false,
                'already_deleted' => true,
            ];
        }

        $failedCleanupJobs = $this->organizationDeletionJobStatusPort->failedCleanupJobs($organizationId, $deletionRunId);
        if ([] !== $failedCleanupJobs) {
            return [
                'stage'               => 'finalize',
                'organization_id'     => $organizationId,
                'deletion_run_id'     => $deletionRunId,
                'blocked'             => true,
                'failed_cleanup_jobs' => $failedCleanupJobs,
            ];
        }

        $runningOrPendingCleanupJobs = $this->organizationDeletionJobStatusPort->runningOrPendingCleanupJobs(
            $organizationId,
            $deletionRunId,
        );
        if ([] !== $runningOrPendingCleanupJobs) {
            return [
                'stage'                => 'finalize',
                'organization_id'      => $organizationId,
                'deletion_run_id'      => $deletionRunId,
                'waiting_cleanup'      => true,
                'pending_cleanup_jobs' => $runningOrPendingCleanupJobs,
            ];
        }

        $remainingSources = $this->sourceDeletionSchedulerPort->sourcesForOrganization($organizationId);
        if ([] !== $remainingSources) {
            return [
                'stage'             => 'finalize',
                'organization_id'   => $organizationId,
                'deletion_run_id'   => $deletionRunId,
                'blocked'           => true,
                'remaining_sources' => count($remainingSources),
            ];
        }

        $remainingPackages = $this->packageDeletionSchedulerPort->packagesForOrganization($organizationId);
        if ([] !== $remainingPackages) {
            return [
                'stage'              => 'finalize',
                'organization_id'    => $organizationId,
                'deletion_run_id'    => $deletionRunId,
                'blocked'            => true,
                'remaining_packages' => count($remainingPackages),
            ];
        }

        $invitationsDeleted = $this->deleteInvitations($organizationId);
        $membersDeleted     = $this->deleteMembers($organizationId);

        $this->organizationRepository->deleteAndFlush($organization);

        return [
            'stage'                         => 'finalize',
            'organization_id'               => $organizationId,
            'deletion_run_id'               => $deletionRunId,
            'deleted'                       => true,
            'invitations_deleted_defensive' => $invitationsDeleted,
            'members_deleted_defensive'     => $membersDeleted,
        ];
    }

    private function deleteInvitations(string $organizationId): int
    {
        $deleted = 0;
        foreach ($this->organizationInvitationRepository->findByOrganization($organizationId) as $organizationInvitation) {
            $this->organizationInvitationRepository->deleteAndFlush($organizationInvitation);
            ++$deleted;
        }

        return $deleted;
    }

    private function deleteMembers(string $organizationId): int
    {
        $deleted = 0;
        foreach ($this->organizationMemberRepository->findByOrganization($organizationId) as $organizationMember) {
            $this->organizationMemberRepository->deleteAndFlush($organizationMember);
            ++$deleted;
        }

        return $deleted;
    }

    private function requireIdentifier(string $value, string $message): string
    {
        $resolved = trim($value);
        if ('' === $resolved) {
            throw new RuntimeException($message);
        }

        return $resolved;
    }
}
