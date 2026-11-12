<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Resource;

use Packio\Release\Api\Dto\ReleaseDto;

final readonly class ReleaseResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromDto(ReleaseDto $releaseDto): array
    {
        return [
            'organization_id'    => $releaseDto->organizationId,
            'package_name'       => $releaseDto->packageName,
            'version'            => $releaseDto->version,
            'reference'          => $releaseDto->reference,
            'composer'           => $releaseDto->composer,
            'source'             => $releaseDto->source,
            'dist'               => $releaseDto->dist,
            'active'             => $releaseDto->active,
            'download_count'     => $releaseDto->downloadCount,
            'created_at'         => $releaseDto->createdAt,
            'released_at'        => $releaseDto->releasedAt,
            'upstream_reference' => $releaseDto->upstreamReference,
            'reference_type'     => $releaseDto->referenceType,
        ];
    }
}
