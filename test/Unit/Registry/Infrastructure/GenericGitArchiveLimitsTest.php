<?php

declare(strict_types=1);

namespace PackioTest\Unit\Registry\Infrastructure;

use Common\Infrastructure\Process\LimitedProcessRunner;
use Common\Infrastructure\Resource\ResourceLimits;
use Packio\Registry\Infrastructure\GenericGitDistArchiveFetcher;
use PackioTest\Support\Fake\OutboundPolicyFixture;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function chmod;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function glob;
use function mkdir;
use function putenv;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class GenericGitArchiveLimitsTest extends TestCase
{
    public function testOversizedArchiveFailsAndRemovesCheckout(): void
    {
        $directory = sys_get_temp_dir() . '/packio-fake-limited-git-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $log = $directory . '/log';
        file_put_contents($directory . '/git', <<<'SH'
            #!/bin/sh
            set -eu
            if [ "$1" = clone ]; then
                printf '%s' "$5" > "$PACKIO_LIMIT_TEST_LOG"
                mkdir -p "$5"
            else
                head -c 10000 /dev/zero > "${3#--output=}"
            fi
            SH);
        chmod($directory . '/git', 0o700);
        $originalPath = getenv('PATH') ?: '';
        putenv('PATH=' . $directory . ':' . $originalPath);
        putenv('PACKIO_LIMIT_TEST_LOG=' . $log);

        try {
            $resourceLimits               = new ResourceLimits(gitWorkspaceBytes: 4096, archiveBytes: 16);
            $genericGitDistArchiveFetcher = new GenericGitDistArchiveFetcher(OutboundPolicyFixture::git(), new LimitedProcessRunner($resourceLimits), $resourceLimits);

            try {
                $genericGitDistArchiveFetcher->fetchArchive('repo', 'main', providerConfig: [
                    'clone_url' => 'https://example.test/repo',
                ]);
                self::fail('Oversized archive was accepted.');
            } catch (RuntimeException $exception) {
                self::assertSame('Failed to build generic Git archive.', $exception->getMessage());
                $checkout = file_get_contents($log);
                self::assertIsString($checkout);
                self::assertDirectoryDoesNotExist(dirname($checkout));
            }
        } finally {
            putenv('PATH=' . $originalPath);
            putenv('PACKIO_LIMIT_TEST_LOG');
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
