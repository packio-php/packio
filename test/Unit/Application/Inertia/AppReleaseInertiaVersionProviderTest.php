<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Inertia;

use Laminas\Diactoros\ServerRequest;
use Packio\Application\Inertia\AppReleaseInertiaVersionProvider;
use Packio\Application\Release\AppReleaseVersion;
use PackioTest\Support\Base\UnitTestCase;
use PHPUnit\Framework\Attributes\Test;

use function file_put_contents;
use function json_encode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class AppReleaseInertiaVersionProviderTest extends UnitTestCase
{
    #[Test]
    public function itProvidesTheReleaseShaForEarlyInertiaVersionChecks(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'packio-release-');
        self::assertIsString($path);
        file_put_contents($path, json_encode([
            'sha' => 'abc1234',
        ], JSON_THROW_ON_ERROR));

        try {
            $appReleaseInertiaVersionProvider = new AppReleaseInertiaVersionProvider(new AppReleaseVersion([
                'release' => [
                    'file' => $path,
                ],
            ]));

            self::assertSame('abc1234', $appReleaseInertiaVersionProvider->currentVersion(new ServerRequest()));
        } finally {
            unlink($path);
        }
    }
}
