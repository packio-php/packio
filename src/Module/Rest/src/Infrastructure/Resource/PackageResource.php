<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Package\Api\Dto\PackageDto;

final readonly class PackageResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(PackageDto $packageDto): array
    {
        return [
            'name'            => $packageDto->name,
            'type'            => $packageDto->type,
            'description'     => $packageDto->description,
            'visibility'      => $packageDto->visibility,
            'active'          => $packageDto->active,
            'source_id'       => '' !== $packageDto->sourceId ? $packageDto->sourceId : null,
            'source_type'     => $packageDto->sourceType,
            'source_url'      => $packageDto->sourceUrl,
            'organization_id' => $packageDto->organizationId,
            'readme_html'     => $packageDto->readmeHtml,
            'download_count'  => $packageDto->downloadCount,
            'created_at'      => $packageDto->createdAt,
            'updated_at'      => $packageDto->updatedAt,
        ];
    }
}
