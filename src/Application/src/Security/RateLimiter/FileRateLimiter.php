<?php

declare(strict_types=1);

namespace Packio\Application\Security\RateLimiter;

use const LOCK_EX;
use const LOCK_UN;

use InvalidArgumentException;
use RuntimeException;

use function array_filter;
use function array_is_list;
use function array_values;
use function clearstatcache;
use function count;
use function dirname;
use function fclose;
use function file_exists;
use function flock;
use function fopen;
use function fstat;
use function ftruncate;
use function fwrite;
use function hash;
use function is_array;
use function is_dir;
use function is_executable;
use function is_file;
use function is_int;
use function is_link;
use function is_resource;
use function is_string;
use function json_decode;
use function json_encode;
use function lstat;
use function max;
use function mkdir;
use function preg_match;
use function rewind;
use function scandir;
use function sort;
use function strcmp;
use function stream_get_contents;
use function strlen;
use function time;
use function trim;
use function unlink;

final readonly class FileRateLimiter implements RateLimiterInterface
{
    private int $retention;

    /**
     * @param array<string, mixed> $rateLimitConfig
     */
    public function __construct(private array $rateLimitConfig)
    {
        $effectiveWindow = max(300, $this->maximumConfiguredWindow($rateLimitConfig));
        $retention       = $rateLimitConfig['retention_seconds'] ?? $effectiveWindow;
        if (! is_int($retention) || $retention < $effectiveWindow) {
            throw new InvalidArgumentException('Rate-limit retention must cover every configured window and be at least 300 seconds.');
        }

        $this->retention = $retention;
    }

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        if (! $this->validPolicy($maxAttempts, $decaySeconds)) {
            return false;
        }

        $directory = $this->storageDirectory();
        if (! $this->ensureDirectory($directory)) {
            return false;
        }

        return $this->withLock($directory, true, false, function() use ($directory, $key, $maxAttempts, $decaySeconds): bool {
            $path   = $this->keyToFile($directory, $key);
            $handle = $this->openData($path, 'c+');
            if (! is_resource($handle)) {
                return false;
            }

            try {
                $timestamps = $this->readTimestamps($handle, true);
                if (! is_array($timestamps)) {
                    return false;
                }

                $now        = time();
                $timestamps = array_values(array_filter(
                    $timestamps,
                    static fn (int $timestamp): bool => $timestamp > $now - $decaySeconds,
                ));
                if (count($timestamps) >= $maxAttempts) {
                    return false;
                }

                $timestamps[] = $now;
                $encoded      = json_encode($timestamps);
                if (! is_string($encoded) || ! ftruncate($handle, 0) || ! rewind($handle) || fwrite($handle, $encoded) !== strlen($encoded)) {
                    return false;
                }

                return true;
            } finally {
                fclose($handle);
            }
        }, true);
    }

    public function isExceeded(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        if (! $this->validPolicy($maxAttempts, $decaySeconds)) {
            return true;
        }

        $directory = $this->storageDirectory();
        $path      = $this->keyToFile($directory, $key);

        try {
            if (! $this->storageDirectoryExists($directory)) {
                return false;
            }
        } catch (RuntimeException) {
            return true;
        }

        return $this->withLock($directory, true, false, function() use ($path, $maxAttempts, $decaySeconds): bool {
            if (! file_exists($path) && ! is_link($path)) {
                return false;
            }
            $handle = $this->openData($path, 'r');
            if (! is_resource($handle)) {
                return true;
            }

            try {
                $timestamps = $this->readTimestamps($handle);
                if (! is_array($timestamps)) {
                    return true;
                }
                $window     = time() - $decaySeconds;
                $timestamps = array_values(array_filter(
                    $timestamps,
                    static fn (int $timestamp): bool => $timestamp > $window,
                ));

                return count($timestamps) >= $maxAttempts;
            } finally {
                fclose($handle);
            }
        }, true, true);
    }

    public function clear(string $key): void
    {
        $directory = $this->storageDirectory();
        if (! $this->storageDirectoryExists($directory)) {
            return;
        }

        $this->withLock($directory, true, false, function() use ($directory, $key): bool {
            $path = $this->keyToFile($directory, $key);
            if (! file_exists($path) && ! is_link($path)) {
                return true;
            }

            if (is_link($path) || ! is_file($path) || ! unlink($path)) {
                throw new RuntimeException('Unable to safely remove rate-limit data file.');
            }

            return true;
        });
    }

    /** @return array{scanned: int, deleted: int, skipped: int} */
    public function cleanup(int $retentionSeconds, int $limit = 1000, bool $dryRun = false): array
    {
        if ($retentionSeconds < $this->retention) {
            throw new InvalidArgumentException('Cleanup retention cannot be shorter than configured rate-limit retention.');
        }

        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Cleanup limit must be between 1 and 1000.');
        }

        $directory = $this->storageDirectory();
        if (! $this->storageDirectoryExists($directory)) {
            return [
                'scanned' => 0,
                'deleted' => 0,
                'skipped' => 0,
            ];
        }

        // ponytail: directory enumeration is O(n); partition only if measured directory size makes maintenance too expensive.
        $names = scandir($directory);
        if (! is_array($names)) {
            throw new RuntimeException('Unable to enumerate rate-limit storage.');
        }
        $names = array_values(array_filter($names, static fn (string $name): bool => 1 === preg_match('/^[a-f0-9]{64}\.json$/D', $name)));
        sort($names, SORT_STRING);

        $cursor = $this->withLock($directory, ! $dryRun, $dryRun, fn (): ?string => $this->readCursor($directory));
        $start  = 0;
        if (is_string($cursor)) {
            foreach ($names as $index => $name) {
                if (strcmp($name, $cursor) > 0) {
                    $start = $index;

                    break;
                }
                $start = $index + 1;
            }

            if ($start >= count($names)) {
                $start = 0;
            }
        }

        $result = [
            'scanned' => 0,
            'deleted' => 0,
            'skipped' => 0,
        ];
        $processed = 0;
        $total     = count($names);
        while ($processed < $limit && $start + $processed < $total) {
            $name = $names[$start + $processed];
            $this->withLock($directory, ! $dryRun, $dryRun, function() use ($directory, $name, $retentionSeconds, $dryRun, &$result): bool {
                $path       = $directory . '/' . $name;
                $cursorPath = $directory . '/.cleanup.cursor';
                if (is_link($cursorPath) || (file_exists($cursorPath) && ! is_file($cursorPath))) {
                    throw new RuntimeException('Rate-limit cleanup cursor is unsafe.');
                }
                ++$result['scanned'];
                if (! preg_match('/^[a-f0-9]{64}\.json$/D', $name)) {
                    ++$result['skipped'];
                } else {
                    $handle = $this->openData($path, 'r');
                    if (! is_resource($handle)) {
                        ++$result['skipped'];
                    } else {
                        try {
                            $timestamps = $this->readTimestamps($handle);
                            if (! is_array($timestamps)) {
                                ++$result['skipped'];
                            } elseif ([] === array_filter($timestamps, static fn (int $timestamp): bool => $timestamp > time() - $retentionSeconds)) {
                                if ($dryRun) {
                                    ++$result['deleted'];
                                } elseif (! unlink($path)) {
                                    throw new RuntimeException('Unable to delete expired rate-limit data.');
                                } else {
                                    ++$result['deleted'];
                                }
                            }
                        } finally {
                            fclose($handle);
                        }
                    }
                }
                if (! $dryRun && ! $this->writeCursor($cursorPath, $name)) {
                    throw new RuntimeException('Unable to update rate-limit cleanup cursor.');
                }

                return true;
            });
            ++$processed;
        }

        return $result;
    }

    private function validPolicy(int $maxAttempts, int $decaySeconds): bool
    {
        return $maxAttempts > 0 && $decaySeconds > 0 && $decaySeconds <= $this->retention;
    }

    /** @param array<string, mixed> $config */
    private function maximumConfiguredWindow(array $config): int
    {
        $maximum = 0;
        foreach ($config as $key => $value) {
            if ('decay_seconds' === $key) {
                if (! is_int($value) || $value <= 0) {
                    throw new InvalidArgumentException('Configured rate-limit windows must be positive integers.');
                }

                $maximum = max($maximum, $value);
            } elseif (is_array($value)) {
                $maximum = max($maximum, $this->maximumConfiguredWindow($value));
            }
        }

        return $maximum;
    }

    private function storageDirectory(): string
    {
        return (string) ($this->rateLimitConfig['storage_dir'] ?? 'runtime/rate-limits');
    }

    private function keyToFile(string $directory, string $key): string
    {
        return $directory . '/' . hash('sha256', $key) . '.json';
    }

    private function storageDirectoryExists(string $directory): bool
    {
        clearstatcache(true, $directory);
        if (is_link($directory) || (file_exists($directory) && ! is_dir($directory))) {
            throw new RuntimeException('Rate-limit storage directory is unsafe or unavailable.');
        }

        if (is_dir($directory)) {
            return true;
        }

        // A failed stat below an unsearchable ancestor does not prove absence.
        $ancestor = dirname($directory);
        while (! is_dir($ancestor)) {
            if (file_exists($ancestor) || is_link($ancestor)) {
                throw new RuntimeException('Rate-limit storage directory is unsafe or unavailable.');
            }
            $parent = dirname($ancestor);
            if ($parent === $ancestor) {
                throw new RuntimeException('Unable to verify rate-limit storage directory.');
            }
            $ancestor = $parent;
            clearstatcache(true, $ancestor);
        }

        if (! is_executable($ancestor)) {
            throw new RuntimeException('Unable to traverse rate-limit storage directory.');
        }

        return false;
    }

    private function ensureDirectory(string $directory): bool
    {
        if (is_dir($directory)) {
            return ! is_link($directory);
        }

        return ! file_exists($directory) && @mkdir($directory, 0o755, true);
    }

    // ponytail: one storage lock serializes filesystem operations; shard only after measured contention.
    /** @param callable(): mixed $callback */
    private function withLock(
        string $directory,
        bool $create,
        bool $dryRun,
        callable $callback,
        bool $failClosed = false,
        mixed $failureValue = false
    ): mixed {
        if (! $this->isSafeStorageDirectory($directory)) {
            if ($failClosed) {
                return $failureValue;
            }

            throw new RuntimeException('Rate-limit storage directory is unsafe or unavailable.');
        }
        $path = $directory . '/.storage.lock';
        if (is_link($path)) {
            if ($failClosed) {
                return $failureValue;
            }

            throw new RuntimeException('Rate-limit storage lock is unsafe or unavailable.');
        }

        if (file_exists($path) && ! is_file($path)) {
            if ($failClosed) {
                return $failureValue;
            }

            throw new RuntimeException('Rate-limit storage lock is not a regular file.');
        }

        if (! $create && ! file_exists($path)) {
            if ($failClosed) {
                return $failureValue;
            }

            throw new RuntimeException('Rate-limit storage lock is unsafe or unavailable.');
        }
        $lock = @fopen($path, $dryRun ? 'r' : 'c+');
        if (! is_resource($lock)) {
            if ($failClosed) {
                return $failureValue;
            }

            throw new RuntimeException('Unable to open stable rate-limit storage lock.');
        }

        if (! $this->isSameRegularFile($lock, $path)) {
            fclose($lock);
            if ($failClosed) {
                return $failureValue;
            }

            throw new RuntimeException('Rate-limit storage lock is not a regular file.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                if ($failClosed) {
                    return $failureValue;
                }

                throw new RuntimeException('Unable to acquire stable rate-limit storage lock.');
            }

            if (! $this->isSafeStorageDirectory($directory) || ! $this->isSameRegularFile($lock, $path)) {
                if ($failClosed) {
                    return $failureValue;
                }

                throw new RuntimeException('Rate-limit storage changed while acquiring its lock.');
            }

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function readCursor(string $directory): ?string
    {
        $path = $directory . '/.cleanup.cursor';
        if (is_link($path)) {
            throw new RuntimeException('Rate-limit cleanup cursor is unsafe.');
        }

        if (! file_exists($path)) {
            return null;
        }
        $handle = $this->openData($path, 'r');
        if (! is_resource($handle)) {
            throw new RuntimeException('Rate-limit cleanup cursor is unsafe.');
        }

        try {
            $cursor = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
        if (! is_string($cursor)) {
            return null;
        }
        $cursor = trim($cursor);
        if ('' === $cursor || 1 !== preg_match('/^[a-f0-9]{64}\.json$/D', $cursor)) {
            return null;
        }

        return $cursor;
    }

    private function writeCursor(string $path, string $value): bool
    {
        $handle = $this->openData($path, 'c+');
        if (! is_resource($handle)) {
            return false;
        }

        try {
            $content = $value . "\n";

            return ftruncate($handle, 0) && rewind($handle) && fwrite($handle, $content) === strlen($content);
        } finally {
            fclose($handle);
        }
    }

    /** @return null|list<int> */
    private function readTimestamps(mixed $handle, bool $emptyIsValid = false): ?array
    {
        rewind($handle);
        $content = stream_get_contents($handle);
        if (! is_string($content)) {
            return null;
        }

        if ('' === $content) {
            return $emptyIsValid ? [] : null;
        }

        $timestamps = json_decode($content);
        if (! is_array($timestamps) || ! array_is_list($timestamps) || [] !== array_filter($timestamps, static fn (mixed $timestamp): bool => ! is_int($timestamp))) {
            return null;
        }

        return $timestamps;
    }

    private function openData(string $path, string $mode): mixed
    {
        if (is_link($path) || (file_exists($path) && ! is_file($path))) {
            return false;
        }
        $handle = @fopen($path, $mode);
        if (! is_resource($handle)) {
            return false;
        }

        if (! $this->isSameRegularFile($handle, $path)) {
            fclose($handle);

            return false;
        }

        return $handle;
    }

    /** @phpstan-impure */
    private function isSafeStorageDirectory(string $directory): bool
    {
        clearstatcache(true, $directory);

        return is_dir($directory) && ! is_link($directory);
    }

    /** @param resource $handle */
    private function isSameRegularFile(mixed $handle, string $path): bool
    {
        clearstatcache(true, $path);
        $handleStat = fstat($handle);
        $pathStat   = lstat($path);

        return is_array($handleStat) && is_array($pathStat) && (($handleStat['mode'] & 0o170000) === 0o100000) && $handleStat['dev'] === $pathStat['dev'] && $handleStat['ino'] === $pathStat['ino'];
    }
}
