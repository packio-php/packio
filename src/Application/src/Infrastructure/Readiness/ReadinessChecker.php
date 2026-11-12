<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Readiness;

use Cycle\Database\Config\PostgresDriverConfig;
use Packio\Admin\Api\RuntimeSettingKeysApiFacadeInterface;
use Packio\Registry\Api\ArchiveStorageApiFacadeInterface;
use Throwable;

use function array_filter;
use function array_values;
use function dirname;
use function fclose;
use function fwrite;
use function hrtime;
use function is_resource;
use function is_string;
use function json_encode;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function stream_set_blocking;
use function strlen;
use function substr;
use function usleep;

/** @phpstan-import-type ProbeConfig from ReadinessProbe */
final class ReadinessChecker
{
    private ?bool $cachedResult = null;
    private int $cachedAt       = 0;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly RuntimeSettingKeysApiFacadeInterface $runtimeSettingKeysApiFacade,
        private readonly ArchiveStorageApiFacadeInterface $archiveStorageApiFacade,
        private readonly int $probeBudgetNanoseconds = 1_800_000_000,
    ) {}

    public function isReady(): bool
    {
        // Owner decision 13-20 (a): /ready caches its probe result for at most one second per worker.
        if (null !== $this->cachedResult && hrtime(true) - $this->cachedAt < 1_000_000_000) {
            return $this->cachedResult;
        }

        // The window starts when the probe starts, so a slow probe cannot stretch it past one second.
        $this->cachedAt     = hrtime(true);
        $this->cachedResult = $this->probe();

        return $this->cachedResult;
    }

    private function probe(): bool
    {
        try {
            $deadline = hrtime(true) + $this->probeBudgetNanoseconds;
            $config   = $this->probeConfig();
            if (null === $config) {
                return false;
            }

            $payload = json_encode($config, JSON_THROW_ON_ERROR);
            if (hrtime(true) >= $deadline) {
                return false;
            }

            return $this->runProbe($payload, $deadline);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return null|ProbeConfig */
    private function probeConfig(): ?array
    {
        $databaseConfig = $this->config['cycle']['db-config'];
        $connection     = $databaseConfig['databases']['default']['connection'];
        $driver         = $databaseConfig['connections'][$connection];
        if (! $driver instanceof PostgresDriverConfig) {
            return null;
        }

        $archiveDirectory     = $this->archiveStorageApiFacade->localArchiveDirectory();
        $schemaFile           = $this->config['cycle']['schema']['compiled']['path'];
        $cachePaths           = [
            dirname($schemaFile),
            $this->config['object_mapper']['cache_dir'],
            $this->config['dependencies']['lazy_services']['proxies_target_dir'],
            $this->config['twig']['cache_dir'],
            $this->config['sirix_mezzio_valinor']['mapper']['cache_dir'],
        ];

        return [
            'dsn'              => $driver->connection->getDsn(),
            'user'             => $driver->connection->getUsername(),
            'password'         => $driver->connection->getPassword(),
            'schemas'          => array_values($driver->schema),
            'schema_file'      => $schemaFile,
            'migrations_dir'   => $this->config['cycle']['migrator']['directory'],
            'migrations_table' => $this->config['cycle']['migrator']['table'],
            'archive'          => $archiveDirectory,
            'cache'            => array_values(array_filter($cachePaths, static fn (mixed $path): bool => is_string($path) && '' !== $path)),
            'settings'         => $this->runtimeSettingKeysApiFacade->settingKeys(),
        ];
    }

    private function runProbe(string $payload, int $deadline): bool
    {
        // A separate process bounds DNS, libpq wire stalls and local filesystem calls,
        // and cannot change the request worker's shared connection or transaction.
        $projectRoot = dirname(__DIR__, 5);
        $process     = proc_open([
            PHP_BINARY, $projectRoot . '/bin/readiness-probe.php',
        ], [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ], $pipes, $projectRoot);
        if (! is_resource($process)) {
            return false;
        }

        try {
            stream_set_blocking($pipes[0], false);
            $offset = 0;
            while (hrtime(true) < $deadline) {
                if (isset($pipes[0]) && $offset < strlen($payload)) {
                    $written = @fwrite($pipes[0], substr($payload, $offset));
                    if (false === $written) {
                        return false;
                    }
                    $offset += $written;
                    if ($offset === strlen($payload)) {
                        fclose($pipes[0]);
                        unset($pipes[0]);
                    }
                }
                $status = proc_get_status($process);
                if (! $status['running']) {
                    return $offset === strlen($payload) && 0 === $status['exitcode'];
                }
                usleep(5_000);
            }

            return false;
        } finally {
            if (isset($pipes[0]) && is_resource($pipes[0])) {
                fclose($pipes[0]);
            }

            if (proc_get_status($process)['running']) {
                proc_terminate($process, 9);
            }
            proc_close($process);
        }
    }
}
