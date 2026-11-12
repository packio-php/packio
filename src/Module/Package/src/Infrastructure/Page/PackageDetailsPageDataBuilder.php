<?php

declare(strict_types=1);

namespace Packio\Package\Infrastructure\Page;

use Packio\Package\Api\Dto\PackageDto;
use Packio\Package\Api\Policy\PackagePolicy;
use Packio\Release\Api\ReleaseApiFacadeInterface;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Shared\Api\Dto\OrganizationMemberDto;
use Packio\Source\Api\Dto\SourceDto;
use Packio\Source\Api\SourceApiFacadeInterface;

use function array_map;

final readonly class PackageDetailsPageDataBuilder
{
    public function __construct(
        private ReleaseApiFacadeInterface $releaseApiFacade,
        private SourceApiFacadeInterface $sourceApiFacade,
        private PackagePolicy $packagePolicy,
    ) {}

    /**
     * @return array{
     *     can_manage_packages: bool,
     *     packageData: array<string, mixed>,
     *     releases: list<array<string, mixed>>
     * }
     */
    public function build(OrganizationDto $organizationDto, PackageDto $packageDto, ?OrganizationMemberDto $organizationMemberDto): array
    {
        $releases = $this->releaseApiFacade->releasesForPackage(
            $packageDto->name,
            true,
            $organizationDto->id,
        );

        $isPrivate = 'private' === $packageDto->visibility;

        $source = '' !== $packageDto->sourceId
            ? $this->sourceApiFacade->findSource($packageDto->sourceId)
            : null;

        if (
            ! $source instanceof SourceDto
            || $source->organizationId !== $organizationDto->id
        ) {
            $source = null;
        }

        return [
            'can_manage_packages' => $this->packagePolicy->canManage(
                $packageDto,
                $organizationDto,
                $organizationMemberDto,
            ),
            'packageData'         => [
                'name'              => $packageDto->name,
                'description'       => $packageDto->description,
                'type'              => $packageDto->type,
                'visibility'        => $packageDto->visibility,
                'anonymous_access'  => $organizationDto->anonymousAccess,
                'active'            => $packageDto->active,
                'source_type'       => $packageDto->sourceType,
                'source_url'        => $packageDto->sourceUrl,
                'created_at'        => $packageDto->createdAt,
                'updated_at'        => $packageDto->updatedAt,
                'readme_html'       => $packageDto->readmeHtml,
                'download_count'    => $packageDto->downloadCount,
                'source_summary'    => $source instanceof SourceDto ? [
                    'id'                      => $source->id,
                    'name'                    => $source->owner . '/' . $source->repository,
                    'type'                    => $source->type,
                    'url'                     => $source->url,
                    'visibility'              => $source->visibility,
                    'active'                  => $source->active,
                    'archived'                => $source->archived,
                    'status'                  => $source->status,
                    'default_ref'             => $source->defaultRef,
                    'last_sync_status'        => $source->lastSyncStatus,
                    'last_successful_sync_at' => $source->lastSuccessfulSyncAt,
                    'last_failed_sync_at'     => $source->lastFailedSyncAt,
                    'last_sync_error'         => $source->lastSyncError,
                ] : null,
                'access_hint'       => $packageDto->active
                    ? ($isPrivate
                        ? 'Requires registry token.'
                        : ($organizationDto->anonymousAccess
                            ? 'Available without authentication.'
                            : 'Requires registry token until anonymous access is enabled for this organization.'))
                    : 'Hidden from registry.',
            ],
            'releases'            => array_map(
                static fn ($release): array => [
                    'version'        => $release->version,
                    'reference'      => $release->reference,
                    'released_at'    => $release->releasedAt,
                    'created_at'     => $release->createdAt,
                    'dist_url'       => $release->dist['url'] ?? '',
                    'source_url'     => $release->source['url'] ?? '',
                    'active'         => $release->active,
                    'download_count' => $release->downloadCount,
                ],
                $releases,
            ),
        ];
    }
}
