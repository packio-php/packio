<?php

declare(strict_types=1);

namespace PackioTest\Support\Builder;

use Packio\Release\Api\Dto\ReleaseDto;

final class ReleaseBuilder extends BaseBuilder
{
    /** @param array<string, mixed> $overrides */
    public static function make(array $overrides = []): ReleaseDto
    {
        $name = $overrides['packageName'] ?? 'vendor/package';

        return new ReleaseDto(
            packageName: $name,
            version: $overrides['version'] ?? '1.0.0',
            reference: $overrides['reference'] ?? self::uniqueToken('ref'),
            composer: $overrides['composer'] ?? [
                'name' => $name,
            ],
            source: $overrides['source'] ?? [
                'type' => 'git',
                'url'  => 'https://example.com/repo',
            ],
            dist: $overrides['dist'] ?? [
                'url' => 'https://example.com/dist',
            ],
            createdAt: $overrides['createdAt'] ?? '',
            organizationId: $overrides['organizationId'] ?? self::uniqueToken('org'),
            releasedAt: $overrides['releasedAt'] ?? null,
            active: $overrides['active'] ?? true,
            downloadCount: $overrides['downloadCount'] ?? 0,
        );
    }
}
