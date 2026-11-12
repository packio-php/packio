<?php

declare(strict_types=1);

namespace PackioTest\Integration\Application\Security;

use const LOCK_EX;
use const LOCK_UN;
use const SIGCONT;
use const SIGSTOP;

use Packio\Application\Security\RateLimiter\FileRateLimiter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function flock;
use function fopen;
use function fstat;
use function hash;
use function hrtime;
use function is_int;
use function is_resource;
use function is_string;
use function mkdir;
use function posix_kill;
use function preg_match;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function random_bytes;
use function rmdir;
use function str_contains;
use function stream_get_contents;
use function sys_get_temp_dir;
use function unlink;

final class RateLimiterCleanupConcurrencyTest extends TestCase
{
    #[Test]
    public function cleanupUnlinksOldSameKeyBeforeQueuedWriterCreatesNewCounterFile(): void
    {
        $directory = sys_get_temp_dir() . '/packio-rate-limit-concurrency-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o755);
        $key           = 'shared-key';
        $dataPath      = $directory . '/' . hash('sha256', $key) . '.json';
        $lockPath      = $directory . '/.storage.lock';
        $cleanup       = null;
        $writer        = null;
        $cleanupPipes  = [];
        $writerPipes   = [];
        $cleanupPid    = null;
        $writerPid     = null;
        $writerStopped = false;
        $lock          = null;
        $lockHeld      = false;
        file_put_contents($dataPath, '[1]');
        $fileRateLimiter = new FileRateLimiter([
            'storage_dir'       => $directory,
            'retention_seconds' => 300,
        ]);

        try {
            $lock = fopen($lockPath, 'c+');
            self::assertNotFalse($lock);
            self::assertTrue(flock($lock, LOCK_EX));
            $lockHeld = true;
            $lockStat = fstat($lock);
            self::assertIsArray($lockStat);
            $inode = (string) $lockStat['ino'];

            $cleanupCode = 'require "vendor/autoload.php"; $l=new \Packio\Application\Security\RateLimiter\FileRateLimiter(["storage_dir"=>$argv[1],"retention_seconds"=>300]); echo json_encode($l->cleanup(300,100));';
            $cleanup     = proc_open([PHP_BINARY, '-r', $cleanupCode, $directory], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $cleanupPipes, dirname(__DIR__, 4));
            self::assertIsResource($cleanup);
            fclose($cleanupPipes[0]);
            $cleanupPid = proc_get_status($cleanup)['pid'];
            self::assertTrue($this->isBlockedOnLock($cleanupPid, $inode), 'cleanup did not reach the stable lock wait barrier');

            $writerCode = 'require "vendor/autoload.php"; $l=new \Packio\Application\Security\RateLimiter\FileRateLimiter(["storage_dir"=>$argv[1],"retention_seconds"=>300]); echo $l->attempt("shared-key",1,300)?"allowed":"blocked";';
            $writer     = proc_open([PHP_BINARY, '-r', $writerCode, $directory], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $writerPipes, dirname(__DIR__, 4));
            self::assertIsResource($writer);
            fclose($writerPipes[0]);
            $writerPid = proc_get_status($writer)['pid'];
            self::assertTrue($this->isBlockedOnLock($writerPid, $inode), 'writer did not queue behind cleanup on the stable lock');
            self::assertTrue(posix_kill($writerPid, SIGSTOP));
            $writerStopped = true;
            self::assertTrue($this->waitUntilStopped($writerPid), 'writer did not reach the stopped-process barrier');

            flock($lock, LOCK_UN);
            $lockHeld = false;
            fclose($lock);
            $lock = null;

            $cleanupOutput = stream_get_contents($cleanupPipes[1]);
            $cleanupError  = stream_get_contents($cleanupPipes[2]);
            self::assertSame('', $cleanupError);
            self::assertSame(0, proc_close($cleanup));
            $cleanup = null;
            self::assertSame('{"scanned":1,"deleted":1,"skipped":0}', $cleanupOutput);
            self::assertFileDoesNotExist($dataPath);

            self::assertTrue(posix_kill($writerPid, SIGCONT));
            $writerStopped = false;
            self::assertSame('allowed', stream_get_contents($writerPipes[1]));
            self::assertSame('', stream_get_contents($writerPipes[2]));
            self::assertSame(0, proc_close($writer));
            $writer = null;
            self::assertTrue($fileRateLimiter->isExceeded($key, 1, 300));
            self::assertFalse($fileRateLimiter->attempt($key, 1, 300));
            self::assertFileExists($dataPath);
        } finally {
            if (is_resource($lock)) {
                if ($lockHeld) {
                    flock($lock, LOCK_UN);
                }
                fclose($lock);
            }

            if ($writerStopped && is_int($writerPid)) {
                @posix_kill($writerPid, SIGCONT);
            }
            $this->stopAndCloseProcess($writer, $writerPipes);
            $this->stopAndCloseProcess($cleanup, $cleanupPipes);
            @unlink($dataPath);
            @unlink($lockPath);
            @unlink($directory . '/.cleanup.cursor');
            @rmdir($directory);
        }
    }

    private function isBlockedOnLock(int $pid, string $inode): bool
    {
        $deadline = hrtime(true) + 5_000_000_000;
        do {
            $locks = @file_get_contents('/proc/locks');
            if (is_string($locks) && str_contains($locks, '-> FLOCK') && 1 === preg_match('/-> FLOCK .*\s' . $pid . '\s+[0-9a-f]+:[0-9a-f]+:' . $inode . '\s/m', $locks)) {
                return true;
            }
        } while (hrtime(true) < $deadline);

        return false;
    }

    private function waitUntilStopped(int $pid): bool
    {
        $deadline = hrtime(true) + 5_000_000_000;
        do {
            $status = @file_get_contents('/proc/' . $pid . '/status');
            if (is_string($status) && 1 === preg_match('/^State:\s+T/m', $status)) {
                return true;
            }
        } while (hrtime(true) < $deadline);

        return false;
    }

    /** @param array<int, resource> $pipes */
    private function stopAndCloseProcess(mixed &$process, array &$pipes): void
    {
        if (is_resource($process)) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $pipes = [];
        if (is_resource($process)) {
            proc_close($process);
            $process = null;
        }
    }
}
