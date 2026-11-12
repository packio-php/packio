<?php

declare(strict_types=1);

namespace Packio\Release\Infrastructure\Mapper;

use Packio\Release\Api\Dto\ReleaseDto;
use Packio\Release\Domain\Entity\Release;

final readonly class ReleaseDtoMapper
{
    public function map(Release $release): ReleaseDto
    {
        return new ReleaseDto(
            packageName: $release->packageName(),
            version: $release->version(),
            reference: $release->reference(),
            composer: $release->composerData(),
            source: $release->sourceData(),
            dist: $this->publicDistData($release),
            createdAt: $release->getCreatedAt()->format(DATE_ATOM),
            organizationId: $release->organizationId(),
            releasedAt: $release->releasedAt()?->format(DATE_ATOM),
            active: $release->isActive(),
            downloadCount: $release->downloadCount(),
            upstreamReference: $release->upstreamReference(),
            referenceType: $release->referenceType(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function publicDistData(Release $release): array
    {
        $distData = $release->distData();
        unset($distData['storage']);

        return $distData;
    }
}
