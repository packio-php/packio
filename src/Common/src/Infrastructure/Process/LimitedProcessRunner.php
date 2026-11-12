<?php

declare(strict_types=1);

namespace Common\Infrastructure\Process;

use Common\Infrastructure\Resource\ResourceLimits;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function array_filter;
use function array_keys;
use function ceil;
use function clearstatcache;
use function fclose;
use function feof;
use function filesize;
use function fread;
use function function_exists;
use function hrtime;
use function is_dir;
use function is_resource;
use function max;
use function min;
use function posix_kill;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function stream_select;
use function stream_set_blocking;
use function strlen;
use function usleep;

final readonly class LimitedProcessRunner
{
    public function __construct(private ResourceLimits $resourceLimits = new ResourceLimits()) {}

    /** @param array<string, string> $environment */
    public function run(
        string $command,
        array $environment,
        ?string $cwd = null,
        ?string $workspace = null,
        ?int $fileLimit = null
    ): ProcessResult {
        if (! function_exists('posix_kill')) {
            throw new RuntimeException('Git operations require the PHP POSIX extension.');
        }
        $budget = $this->resourceLimits->gitWorkspaceBytes - $this->workspaceSize($workspace);
        if ($budget <= 0) {
            throw new RuntimeException('Git workspace exceeds the size limit.');
        }
        $process = proc_open([
            PHP_BINARY,
            __DIR__ . '/limited-process.php',
            (string) min($fileLimit ?? $budget, $budget),
            (string) max(1, (int) ceil($this->resourceLimits->gitSeconds)),
            (string) $this->resourceLimits->gitMemoryBytes,
            $command,
            ...array_keys(array_filter($environment, static fn (string $value): bool => '' === $value)),
        ], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $cwd, $environment);
        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start subprocess.');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = [
            1 => '',
            2 => '',
        ];
        $started            = hrtime(true);
        $nextWorkspaceCheck = 0;
        $status             = proc_get_status($process);
        $pid                = $status['pid'];

        try {
            while (true) {
                if ((hrtime(true) - $started) / 1_000_000_000 >= $this->resourceLimits->gitSeconds) {
                    throw new RuntimeException('Git command exceeded the time limit.');
                }

                // The scan walks the whole tree (~140 ms on 14k files); run it at most once per second.
                if (hrtime(true) >= $nextWorkspaceCheck) {
                    $this->assertWorkspaceWithinLimit($workspace);
                    $nextWorkspaceCheck = hrtime(true) + 1_000_000_000;
                }

                // Read both pipes on every iteration; neither can starve the other.
                foreach ([1, 2] as $index) {
                    $chunk = fread($pipes[$index], 8192);
                    if (false === $chunk) {
                        throw new RuntimeException('Failed to read subprocess output.');
                    }
                    $output[$index] .= $chunk;
                    if (strlen($output[1]) + strlen($output[2]) > $this->resourceLimits->upstreamBytes) {
                        throw new RuntimeException('Git output exceeds the size limit.');
                    }
                }
                $status = proc_get_status($process);
                if (! $status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                    break;
                }
                $read = array_filter([$pipes[1], $pipes[2]], static fn ($pipe): bool => ! feof($pipe));
                if ([] !== $read) {
                    $write  = null;
                    $except = null;
                    @stream_select($read, $write, $except, 0, 20_000);
                } else {
                    usleep(20_000);
                }
            }

            $this->assertWorkspaceWithinLimit($workspace);

            return new ProcessResult($output[1], $output[2], $status['exitcode']);
        } finally {
            // The wrapper creates a session before exec; terminate Git and its helpers together.
            @posix_kill(-$pid, SIGKILL);
            if ($status['running']) {
                proc_terminate($process, SIGKILL);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }

    public function workspaceSize(?string $directory): int
    {
        if (null === $directory || ! is_dir($directory)) {
            return 0;
        }
        clearstatcache();
        $bytes    = 0;
        $entries  = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (++$entries > 100_000) {
                throw new RuntimeException('Git workspace has too many files.');
            }

            if (! $file->isLink() && $file->isFile()) {
                $bytes += @filesize($file->getPathname()) ?: 0;
            }

            if ($bytes > $this->resourceLimits->gitWorkspaceBytes) {
                break;
            }
        }

        return $bytes;
    }

    private function assertWorkspaceWithinLimit(?string $workspace): void
    {
        if ($this->workspaceSize($workspace) > $this->resourceLimits->gitWorkspaceBytes) {
            throw new RuntimeException('Git workspace exceeds the size limit.');
        }
    }
}
