<?php

declare(strict_types=1);

namespace PackioTest\Unit\Release\Infrastructure\Mapper;

use Cake\Chronos\Chronos;
use Packio\Release\Domain\Entity\Release;
use Packio\Release\Infrastructure\Mapper\ReleaseDtoMapper;
use PackioTest\Support\Base\UnitTestCase;

final class ReleaseDtoMapperTest extends UnitTestCase
{
    public function testStripsDistStorageMetadataFromFacadeDto(): void
    {
        $release = Release::create(
            organizationId: 'org-1',
            packageName: 'vendor/package',
            version: '1.0.0',
            reference: 'ref',
            composerData: [
                'name' => 'vendor/package',
            ],
            sourceData: [
                'type' => 'git',
            ],
            distData: [
                'type'      => 'zip',
                'url'       => 'https://source.example/archive.zip',
                'reference' => 'ref',
                'storage'   => [
                    'driver'           => 'local',
                    'bucket'           => null,
                    'key'              => 'private/org/org-1/dist/vendor/package/1.0.0_ref.zip',
                    'content_type'     => 'application/zip',
                    'size'             => 13,
                    'sha256'           => 'abc123',
                    'etag'             => '"abc123"',
                    'stored_at'        => '2026-06-10T12:00:00+00:00',
                    'source_provider'  => 'github',
                    'source_reference' => 'ref',
                ],
            ],
            releasedAt: new Chronos('2024-04-01T12:00:00+00:00'),
        );
        $release->setCreatedAt(new Chronos('2026-06-10T12:00:00+00:00'));

        $releaseDto = new ReleaseDtoMapper()->map($release);

        self::assertSame([
            'type'      => 'zip',
            'url'       => 'https://source.example/archive.zip',
            'reference' => 'ref',
        ], $releaseDto->dist);
        self::assertSame('2026-06-10T12:00:00+00:00', $releaseDto->createdAt);
        self::assertSame('2024-04-01T12:00:00+00:00', $releaseDto->releasedAt);
    }
}
