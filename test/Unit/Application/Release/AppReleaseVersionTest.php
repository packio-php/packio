<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Release;

use Packio\Application\Release\AppReleaseVersion;
use PackioTest\Support\Base\UnitTestCase;

use function file_put_contents;
use function json_encode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class AppReleaseVersionTest extends UnitTestCase
{
    public function testReadsGeneratedReleaseMetadataFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'packio-release-');
        self::assertIsString($path);

        file_put_contents($path, json_encode([
            'version'  => '2.1.0-dev.4+abc1234',
            'channel'  => 'dev',
            'sha'      => 'abc1234',
            'branch'   => 'main',
            'built_at' => '2026-05-23T12:00:00Z',
            'distance' => 4,
            'dirty'    => false,
            'tag'      => 'v2.0.0',
        ], JSON_THROW_ON_ERROR));

        try {
            $appReleaseVersion = new AppReleaseVersion([
                'release' => [
                    'file' => $path,
                ],
            ]);

            self::assertSame([
                'version'  => '2.1.0-dev.4+abc1234',
                'number'   => '2.1.0-dev.4+abc1234',
                'channel'  => 'dev',
                'sha'      => 'abc1234',
                'branch'   => 'main',
                'built_at' => '2026-05-23T12:00:00Z',
                'distance' => 4,
                'dirty'    => false,
                'tag'      => 'v2.0.0',
                'display'  => '2.1.0-dev.4+abc1234 (abc1234)',
            ], $appReleaseVersion->toArray());
        } finally {
            unlink($path);
        }
    }
}
