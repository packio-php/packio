<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Security\RateLimiter;

use InvalidArgumentException;
use Packio\Application\Security\RateLimiter\FileRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function chmod;
use function dechex;
use function dirname;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function hash;
use function is_dir;
use function is_resource;
use function mkdir;
use function proc_close;
use function proc_open;
use function random_bytes;
use function rmdir;
use function str_pad;
use function str_repeat;
use function stream_get_contents;
use function symlink;
use function sys_get_temp_dir;
use function time;
use function touch;
use function unlink;

#[CoversClass(FileRateLimiter::class)]
final class FileRateLimiterTest extends TestCase
{
    #[Test]
    public function checkingAnUnknownKeyDoesNotCreateRateLimitStorage(): void
    {
        $storageDirectory = sys_get_temp_dir() . '/packio-rate-limit-check-' . bin2hex(random_bytes(8));
        $fileRateLimiter  = new FileRateLimiter([
            'storage_dir' => $storageDirectory,
        ]);

        try {
            self::assertFalse($fileRateLimiter->isExceeded('api_authentication:ip:203.0.113.10', 60, 60));
            self::assertFalse(is_dir($storageDirectory));
        } finally {
            @rmdir($storageDirectory);
        }
    }

    #[Test]
    public function missingStorageUnderAnInaccessibleAncestorFailsClosed(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-permissions-' . bin2hex(random_bytes(8));
        $blocked   = $directory . '/blocked';
        self::assertTrue(mkdir($directory, 0o755));
        self::assertTrue(mkdir($blocked, 0o700));
        self::assertTrue(chmod($blocked, 0));
        $code = <<<'PHP'
            require 'vendor/autoload.php';
            if (posix_geteuid() === 0 && (!posix_setgid(65534) || !posix_setuid(65534))) {
                exit(2);
            }
            $limiter = new \Packio\Application\Security\RateLimiter\FileRateLimiter(['storage_dir' => $argv[1]]);
            $clearFailed = $cleanupFailed = false;
            try { $limiter->clear('key'); } catch (\RuntimeException) { $clearFailed = true; }
            try { $limiter->cleanup(3600, 1, true); } catch (\RuntimeException) { $cleanupFailed = true; }
            echo json_encode([$limiter->isExceeded('key', 1, 60), $limiter->attempt('key', 1, 60), $clearFailed, $cleanupFailed]);
            PHP;
        $process = null;
        $pipes   = [];

        try {
            $process = proc_open([PHP_BINARY, '-r', $code, $blocked . '/missing/nested'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 5));
            self::assertIsResource($process);
            fclose($pipes[0]);
            self::assertSame('[true,false,true,true]', stream_get_contents($pipes[1]));
            self::assertSame('', stream_get_contents($pipes[2]));
            self::assertSame(0, proc_close($process));
            $process = null;
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            if (is_resource($process)) {
                proc_close($process);
            }
            chmod($blocked, 0o700);
            @rmdir($blocked);
            @rmdir($directory);
        }
    }

    #[Test]
    public function genuinelyAbsentNestedStorageRemainsAnUncreatedNoop(): void
    {
        $directory         = sys_get_temp_dir() . '/packio-rate-limit-missing-' . bin2hex(random_bytes(8));
        $fileRateLimiter   = new FileRateLimiter([
            'storage_dir' => $directory . '/missing/nested',
        ]);
        self::assertFalse($fileRateLimiter->isExceeded('key', 1, 60));
        self::assertSame([
            'scanned' => 0,
            'deleted' => 0,
            'skipped' => 0,
        ], $fileRateLimiter->cleanup(3600, 1, true));
        $fileRateLimiter->clear('key');
        self::assertFalse(is_dir($directory));
    }

    #[Test]
    public function checkingAnExistingKeyDoesNotRewriteItsStoredHits(): void
    {
        $storageDirectory = sys_get_temp_dir() . '/packio-rate-limit-check-' . bin2hex(random_bytes(8));
        $key              = 'api_authentication:ip:203.0.113.10';
        $file             = $storageDirectory . '/' . hash('sha256', $key) . '.json';
        self::assertTrue(mkdir($storageDirectory, 0o755));
        file_put_contents($file, '[' . time() . ']');
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir' => $storageDirectory,
        ]);

        try {
            $before = file_get_contents($file);

            self::assertTrue($fileRateLimiter->isExceeded($key, 1, 60));
            self::assertSame($before, file_get_contents($file));
        } finally {
            @unlink($file);
            @unlink($storageDirectory . '/.storage.lock');
            @rmdir($storageDirectory);
        }
    }

    #[Test]
    public function cleanupDeletesOnlyExpiredRegularHashedFilesAndSupportsDryRun(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-cleanup-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o755);
        $expired = $directory . '/' . hash('sha256', 'expired') . '.json';
        $active  = $directory . '/' . hash('sha256', 'active') . '.json';
        file_put_contents($expired, '[1]');
        file_put_contents($active, '[' . (time() + 3600) . ']');
        touch($active, time() - 1000);
        file_put_contents($directory . '/.storage.lock', '');
        touch($expired, time() - 1000);
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 300,
        ]);

        try {
            self::assertSame([
                'scanned' => 2,
                'deleted' => 1,
                'skipped' => 0,
            ], $fileRateLimiter->cleanup(300, 100, true));
            self::assertFileExists($expired);
            self::assertFileDoesNotExist($directory . '/.cleanup.cursor');
            self::assertSame('', file_get_contents($directory . '/.storage.lock'));
            self::assertSame([
                'scanned' => 2,
                'deleted' => 1,
                'skipped' => 0,
            ], $fileRateLimiter->cleanup(300, 100));
            self::assertFileDoesNotExist($expired);
            self::assertFileExists($active);
        } finally {
            @unlink($expired);
            @unlink($active);
            @unlink($directory . '/.storage.lock');
            @unlink($directory . '/.cleanup.cursor');
            @rmdir($directory);
        }
    }

    #[Test]
    public function rejectsRetentionBelowEffectiveRateLimitWindow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FileRateLimiter([
            'retention_seconds' => 299,
            'csrf'              => [
                'decay_seconds' => 300,
            ],
        ]);
    }

    #[Test]
    public function rejectsInvalidConfiguredDecayWindows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FileRateLimiter([
            'login' => [
                'decay_seconds' => 0,
            ],
        ]);
    }

    #[Test]
    public function malformedCounterFailsClosedForBothReadAndWrite(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-malformed-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o755);
        $key  = 'malformed';
        $path = $directory . '/' . hash('sha256', $key) . '.json';
        file_put_contents($path, '{}');
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 300,
        ]);

        try {
            self::assertFalse($fileRateLimiter->attempt($key, 1, 60));
            self::assertTrue($fileRateLimiter->isExceeded($key, 1, 60));
            foreach (['{"0":1}', '[1.5]', '["1"]'] as $malformed) {
                file_put_contents($path, $malformed);
                self::assertFalse($fileRateLimiter->attempt($key, 1, 60));
                self::assertTrue($fileRateLimiter->isExceeded($key, 1, 60));
            }
        } finally {
            @unlink($path);
            @unlink($directory . '/.storage.lock');
            @rmdir($directory);
        }
    }

    #[Test]
    public function invalidWindowsFailClosed(): void
    {
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => sys_get_temp_dir() . '/packio-rate-limit-invalid-' . bin2hex(random_bytes(8)),
            'retention_seconds' => 300,
        ]);
        self::assertFalse($fileRateLimiter->attempt('key', 1, 0));
        self::assertTrue($fileRateLimiter->isExceeded('key', 1, 301));
        self::assertFalse($fileRateLimiter->attempt('key', 0, 60));
        self::assertTrue($fileRateLimiter->isExceeded('key', 0, 60));
    }

    #[Test]
    public function cleanupSkipsMalformedNamesAndSymlinksAndRejectsOutOfRangeLimits(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-unsafe-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o755);
        file_put_contents($directory . '/not-a-hash.json', '[1]');
        $target = $directory . '/target';
        file_put_contents($target, '[1]');
        $link = $directory . '/' . hash('sha256', 'linked') . '.json';
        symlink($target, $link);
        $malformed = $directory . '/' . str_repeat('e', 64) . '.json';
        file_put_contents($malformed, '{invalid');
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 300,
        ]);

        try {
            self::assertSame([
                'scanned' => 2,
                'deleted' => 0,
                'skipped' => 2,
            ], $fileRateLimiter->cleanup(300, 10));

            foreach ([0, 1001] as $invalidLimit) {
                try {
                    $fileRateLimiter->cleanup(300, $invalidLimit);
                } catch (InvalidArgumentException) {
                    continue;
                }
                self::fail('Expected an out-of-range cleanup limit to be rejected.');
            }
        } finally {
            @unlink($link);
            @unlink($target);
            @unlink($malformed);
            @unlink($directory . '/not-a-hash.json');
            @unlink($directory . '/.storage.lock');
            @unlink($directory . '/.cleanup.cursor');
            @rmdir($directory);
        }
    }

    #[Test]
    public function configuredRetentionCanPreserveHistoricalLongerWindow(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-history-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o755);
        $path = $directory . '/' . hash('sha256', 'active') . '.json';
        file_put_contents($path, '[' . (time() - 600) . ']');
        touch($path, time() - 1000);
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 3600,
            'login'             => [
                'decay_seconds' => 60,
            ],
        ]);

        try {
            self::assertSame([
                'scanned' => 1,
                'deleted' => 0,
                'skipped' => 0,
            ], $fileRateLimiter->cleanup(3600, 10));
            self::assertFileExists($path);
        } finally {
            @unlink($path);
            @unlink($directory . '/.storage.lock');
            @unlink($directory . '/.cleanup.cursor');
            @rmdir($directory);
        }
    }

    #[Test]
    public function cleanupCursorReachesExpiredFileAfterOneThousandActiveFiles(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-cursor-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o755);
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 300,
        ]);
        for ($index = 0; $index < 1000; ++$index) {
            file_put_contents($directory . '/' . str_pad(dechex($index), 64, '0', STR_PAD_LEFT) . '.json', '[' . time() . ']');
        }
        $expired = $directory . '/' . str_repeat('f', 64) . '.json';
        file_put_contents($expired, '[1]');
        touch($expired, time() - 1000);

        try {
            self::assertSame([
                'scanned' => 1000,
                'deleted' => 0,
                'skipped' => 0,
            ], $fileRateLimiter->cleanup(300, 1000));
            self::assertFileExists($expired);
            self::assertSame([
                'scanned' => 1,
                'deleted' => 1,
                'skipped' => 0,
            ], $fileRateLimiter->cleanup(300, 1000));
            self::assertFileDoesNotExist($expired);
            $cursor = file_get_contents($directory . '/.cleanup.cursor');
            self::assertSame([
                'scanned' => 3,
                'deleted' => 0,
                'skipped' => 0,
            ], $fileRateLimiter->cleanup(300, 3, true));
            self::assertSame($cursor, file_get_contents($directory . '/.cleanup.cursor'));
            self::assertSame([
                'scanned' => 1,
                'deleted' => 0,
                'skipped' => 0,
            ], $fileRateLimiter->cleanup(300, 1));
        } finally {
            foreach (glob($directory . '/*.json') ?: [] as $file) {
                @unlink($file);
            }
            @unlink($directory . '/.storage.lock');
            @unlink($directory . '/.cleanup.cursor');
            @rmdir($directory);
        }
    }

    #[Test]
    public function cleanupCursorIgnoresNamesThatCannotBeStoredAsValidCursorValues(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-invalid-cursor-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o755);
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 300,
        ]);
        for ($index = 0; $index < 1000; ++$index) {
            file_put_contents($directory . '/000-invalid-' . $index . '.json', 'ignored');
        }
        $expired = $directory . '/' . str_repeat('f', 64) . '.json';
        file_put_contents($expired, '[1]');

        try {
            self::assertSame([
                'scanned' => 1,
                'deleted' => 1,
                'skipped' => 0,
            ], $fileRateLimiter->cleanup(300, 1));
            self::assertFileDoesNotExist($expired);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @unlink($directory . '/.storage.lock');
            @unlink($directory . '/.cleanup.cursor');
            @rmdir($directory);
        }
    }

    #[Test]
    public function storageLockSymlinkFailsClosedAndClearReportsTheUnsafePath(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-lock-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o755);
        $target = $directory . '/outside';
        file_put_contents($target, '');
        symlink($target, $directory . '/.storage.lock');
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 300,
        ]);

        try {
            self::assertFalse($fileRateLimiter->attempt('key', 1, 60));
            self::assertTrue($fileRateLimiter->isExceeded('key', 1, 60));
            $this->expectException(RuntimeException::class);
            $fileRateLimiter->clear('key');
        } finally {
            @unlink($directory . '/.storage.lock');
            @unlink($target);
            @rmdir($directory);
        }
    }

    #[Test]
    public function cleanupRejectsSymlinkCursorWithoutFollowingIt(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-cursor-link-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o755);
        $target = $directory . '/outside';
        file_put_contents($target, 'sentinel');
        symlink($target, $directory . '/.cleanup.cursor');
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 300,
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $fileRateLimiter->cleanup(300);
        } finally {
            @unlink($directory . '/.cleanup.cursor');
            @unlink($directory . '/.storage.lock');
            @unlink($target);
            @rmdir($directory);
        }
    }

    #[Test]
    public function cleanupRejectsDanglingSymlinkCursor(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-dangling-cursor-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o755);
        symlink($directory . '/missing-target', $directory . '/.cleanup.cursor');
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 300,
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $fileRateLimiter->cleanup(300);
        } finally {
            @unlink($directory . '/.cleanup.cursor');
            @unlink($directory . '/.storage.lock');
            @rmdir($directory);
        }
    }

    #[Test]
    public function dryRunWithExistingStorageAndMissingLockFailsWithoutCreatingAnything(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-dryrun-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o755);
        $path = $directory . '/' . hash('sha256', 'active') . '.json';
        file_put_contents($path, '[' . time() . ']');
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 300,
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $fileRateLimiter->cleanup(300, 10, true);
        } finally {
            self::assertFileExists($path);
            self::assertFileDoesNotExist($directory . '/.storage.lock');
            self::assertFileDoesNotExist($directory . '/.cleanup.cursor');
            @unlink($path);
            @rmdir($directory);
        }
    }

    #[Test]
    public function unsafeStoragePathFailsClosedInsteadOfLookingEmpty(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-path-' . bin2hex(random_bytes(8));
        file_put_contents($directory, 'sentinel');
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 300,
        ]);

        try {
            self::assertTrue($fileRateLimiter->isExceeded('key', 1, 60));
            $this->expectException(RuntimeException::class);
            $fileRateLimiter->cleanup(300);
        } finally {
            @unlink($directory);
        }
    }
}
