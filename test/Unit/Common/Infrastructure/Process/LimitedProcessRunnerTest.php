<?php

declare(strict_types=1);

namespace PackioTest\Unit\Common\Infrastructure\Process;

use Common\Infrastructure\Process\LimitedProcessRunner;
use Common\Infrastructure\Process\ProcessResult;
use Common\Infrastructure\Resource\ResourceLimits;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function clearstatcache;
use function escapeshellarg;
use function filesize;
use function glob;
use function microtime;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function usleep;
use function var_export;

final class LimitedProcessRunnerTest extends TestCase
{
    public function testPreservesEmptyGitConfigurationValues(): void
    {
        $processResult = new LimitedProcessRunner()->run('git config --get http.proxy', [
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL'   => '/dev/null',
            'GIT_CONFIG_COUNT'    => '1',
            'GIT_CONFIG_KEY_0'    => 'http.proxy',
            'GIT_CONFIG_VALUE_0'  => '',
        ]);

        self::assertSame(0, $processResult->exitCode);
        self::assertSame("\n", $processResult->stdout);
        self::assertSame('', $processResult->stderr);
    }

    public function testDrainsBothPipesWithoutDeadlock(): void
    {
        $processResult = $this->runPhp('fwrite(STDERR, str_repeat("e", 200000)); fwrite(STDOUT, str_repeat("o", 200000)); exit(7);');
        self::assertSame(7, $processResult->exitCode);
        self::assertSame(str_repeat('e', 200000), $processResult->stderr);
        self::assertSame(str_repeat('o', 200000), $processResult->stdout);
    }

    public function testTimeoutTerminatesProcess(): void
    {
        $started = microtime(true);

        try {
            $this->runPhp('sleep(30);', new ResourceLimits(gitSeconds: 0.15));
            self::fail('Timeout did not stop process.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('time limit', $exception->getMessage());
            self::assertLessThan(3.0, microtime(true) - $started);
        }
    }

    public function testMemoryLimitSurvivesExecAndPreventsLargeAllocation(): void
    {
        // No ini and no shared extensions: a CI PHP with many extensions does not start within 128M.
        $processResult = $this->runPhp(
            'ini_set("memory_limit", "-1"); preg_match("/^Max address space\s+(\d+)/m", file_get_contents("/proc/self/limits"), $m); echo $m[1], "\n"; $data = str_repeat("x", 268435456);',
            new ResourceLimits(gitMemoryBytes: 134217728),
            '-n -d display_errors=stderr',
        );
        self::assertStringStartsWith("134217728\n", $processResult->stdout, sprintf('exit %d, stderr: %s', $processResult->exitCode, $processResult->stderr));
        self::assertNotSame(0, $processResult->exitCode);
    }

    public function testOutputLimitStopsProcess(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Git output exceeds');
        $this->runPhp('while(true) { fwrite(STDERR, str_repeat("x", 8192)); }', new ResourceLimits(upstreamBytes: 1024));
    }

    public function testKernelFileSizeLimitPreventsUnboundedSingleFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'packio-file-limit-');

        try {
            $result = $this->runPhp('file_put_contents(' . var_export($path, true) . ', str_repeat("x", 100000));', new ResourceLimits(gitWorkspaceBytes: 4096));
            clearstatcache();
            self::assertLessThanOrEqual(4096, filesize($path));
            self::assertNotSame(0, $result->exitCode);
        } finally {
            unlink($path);
        }
    }

    public function testAggregateWorkspaceLimitStopsMultipleFiles(): void
    {
        $path = sys_get_temp_dir() . '/packio-workspace-' . bin2hex(random_bytes(8));
        mkdir($path);

        try {
            $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('file_put_contents("one", str_repeat("x", 3000)); file_put_contents("two", str_repeat("x", 3000)); sleep(30);');
            $started = microtime(true);

            try {
                new LimitedProcessRunner(new ResourceLimits(gitWorkspaceBytes: 4096))->run($command, [], $path, $path);
                self::fail('Workspace limit did not stop process.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('workspace exceeds', $exception->getMessage());
                self::assertLessThan(5.0, microtime(true) - $started);
            }
        } finally {
            foreach (glob($path . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($path);
        }
    }

    public function testWorkspaceLimitIsCheckedAfterProcessExits(): void
    {
        $path = sys_get_temp_dir() . '/packio-workspace-' . bin2hex(random_bytes(8));
        mkdir($path);

        try {
            $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('file_put_contents("one", str_repeat("x", 3000)); file_put_contents("two", str_repeat("x", 3000));');
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('workspace exceeds');
            new LimitedProcessRunner(new ResourceLimits(gitWorkspaceBytes: 4096))->run($command, [], $path, $path);
        } finally {
            foreach (glob($path . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($path);
        }
    }

    public function testTimeoutAlsoStopsSpawnedChild(): void
    {
        $path   = tempnam(sys_get_temp_dir(), 'packio-child-limit-');
        $script = '$pid = pcntl_fork(); if ($pid === 0) { while (true) { file_put_contents(' . var_export($path, true) . ', "x", FILE_APPEND); usleep(10000); } } sleep(30);';

        try {
            try {
                $this->runPhp($script, new ResourceLimits(gitSeconds: 0.3));
                self::fail('Timeout did not stop process group.');
            } catch (RuntimeException) {
                clearstatcache();
                $size = filesize($path);
                self::assertGreaterThan(0, $size);
                usleep(100000);
                clearstatcache();
                self::assertSame($size, filesize($path));
            }
        } finally {
            unlink($path);
        }
    }

    private function runPhp(
        string $script,
        ResourceLimits $resourceLimits = new ResourceLimits(),
        string $phpOptions = ''
    ): ProcessResult {
        return new LimitedProcessRunner($resourceLimits)->run(escapeshellarg(PHP_BINARY) . ' ' . $phpOptions . ' -r ' . escapeshellarg($script), []);
    }
}
