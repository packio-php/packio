<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Page;

use Packio\Audit\Api\AuditApiFacadeInterface;
use Packio\Audit\Api\Dto\AuditEntryDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Package\Api\PackageApiFacadeInterface;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Worker\Api\JobApiFacadeInterface;

use function array_filter;
use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function strcmp;
use function usort;

final readonly class AdminDashboardPageDataBuilder
{
    private const int FAILED_SOURCES_LIMIT = 5;

    private const int RECENT_EVENTS_LIMIT = 15;

    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private PackageApiFacadeInterface $packageApiFacade,
        private SourceApiFacadeInterface $sourceApiFacade,
        private JobApiFacadeInterface $jobApiFacade,
        private AuditApiFacadeInterface $auditApiFacade,
        private AdminOrganizationPresenter $adminOrganizationPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $organizations = $this->organizationIndex();
        $failedSources = $this->failedSources();

        return [
            'stats'                => [
                'users_count'         => $this->identityApiFacade->usersCount(),
                'admins_count'        => $this->identityApiFacade->adminsCount(),
                'organizations_count' => $this->organizationApiFacade->countOrganizations(),
                'packages_count'      => $this->packageApiFacade->countPackages(),
                'sources_count'       => $this->sourceApiFacade->countSources(),
            ],
            'queue_progress'       => $this->jobApiFacade->queueProgress(),
            'auth_mail_progress'   => $this->jobApiFacade->queueProgress('system:identity'),
            'failed_sources'       => array_map(
                static fn (SourceDto $sourceDto): array => [
                    'id'                  => $sourceDto->id,
                    'name'                => '' !== $sourceDto->displayName
                        ? $sourceDto->displayName
                        : $sourceDto->owner . '/' . $sourceDto->repository,
                    'organization'        => $organizations[$sourceDto->organizationId] ?? null,
                    'last_failed_sync_at' => $sourceDto->lastFailedSyncAt,
                    'last_sync_error'     => $sourceDto->lastSyncError,
                ],
                array_slice($failedSources, 0, self::FAILED_SOURCES_LIMIT),
            ),
            'failed_sources_count' => count($failedSources),
            'recent_events'        => $this->recentEvents($organizations),
        ];
    }

    /**
     * @return list<SourceDto>
     */
    private function failedSources(): array
    {
        // shortcut: filters all sources in PHP like the admin sources page; move to SQL when sources reach tens of thousands.
        $failedSources = array_values(array_filter(
            $this->sourceApiFacade->allSources(),
            static fn (SourceDto $sourceDto): bool => 'failed' === $sourceDto->lastSyncStatus
                && ! $sourceDto->archived
                && 'active' === $sourceDto->status,
        ));
        usort(
            $failedSources,
            static fn (SourceDto $left, SourceDto $right): int => strcmp($right->lastFailedSyncAt, $left->lastFailedSyncAt),
        );

        return $failedSources;
    }

    /**
     * @param array<string, array{id: string, slug: string, name: string, status: string}> $organizations
     *
     * @return list<array{id: string, event: string, occurred_at: string, actor_name: string, organization: null|array{id: string, slug: string, name: string, status: string}}>
     */
    private function recentEvents(array $organizations): array
    {
        $auditEntryDtos = $this->auditApiFacade->recent(self::RECENT_EVENTS_LIMIT);
        $actorIds       = array_values(array_unique(array_filter(
            array_map(static fn (AuditEntryDto $auditEntryDto): string => $auditEntryDto->actorId, $auditEntryDtos),
            static fn (string $actorId): bool => '' !== $actorId,
        )));

        $actorNames = [];
        if ([] !== $actorIds) {
            foreach ($this->identityApiFacade->findAllUsersByIds($actorIds) as $userDto) {
                $actorNames[$userDto->id] = $userDto->displayName;
            }
        }

        return array_map(
            static fn (AuditEntryDto $auditEntryDto): array => [
                'id'           => $auditEntryDto->id,
                'event'        => $auditEntryDto->event,
                'occurred_at'  => $auditEntryDto->occurredAt,
                'actor_name'   => '' === $auditEntryDto->actorId
                    ? 'System'
                    : $actorNames[$auditEntryDto->actorId] ?? 'Deleted user',
                'organization' => $organizations[$auditEntryDto->organizationId] ?? null,
            ],
            $auditEntryDtos,
        );
    }

    /**
     * @return array<string, array{id: string, slug: string, name: string, status: string}>
     */
    private function organizationIndex(): array
    {
        $organizations = [];
        // shortcut: loads every organization per dashboard request like admin jobs; switch to a by-ids lookup when organizations reach thousands.
        foreach ($this->organizationApiFacade->allOrganizations() as $organizationDto) {
            $organizations[$organizationDto->id] = $this->adminOrganizationPresenter->present($organizationDto);
        }

        return $organizations;
    }
}
