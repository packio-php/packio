<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Routing;

use const PHP_BINARY;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function escapeshellarg;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function is_resource;
use function proc_close;
use function proc_open;
use function sprintf;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function var_export;

final class RefreshRouteCacheScriptTest extends TestCase
{
    #[Test]
    public function itRemovesAStaleRouteArtifactBeforeWarmingAReplacement(): void
    {
        $cacheFile  = tempnam(sys_get_temp_dir(), 'packio-route-cache-');
        $configFile = tempnam(sys_get_temp_dir(), 'packio-route-cache-config-');
        self::assertIsString($cacheFile);
        self::assertIsString($configFile);

        try {
            file_put_contents($cacheFile, '<?php return [\'middleware\' => \'ApiTokenAuthenticationMiddleware\'];');
            file_put_contents($configFile, '<?php return ' . var_export([
                'routing_attributes' => [
                    'cache' => [
                        'enabled' => true,
                        'file'    => $cacheFile,
                    ],
                ],
            ], true) . ';');

            $warmupCommand = sprintf(
                '%s -r %s %s',
                escapeshellarg(PHP_BINARY),
                escapeshellarg('file_put_contents($argv[1], "<?php return [\\\'middleware\\\' => \\\'ApiAuthenticationContextMiddleware\\\'];");'),
                escapeshellarg($cacheFile),
            );
            $result = $this->runRefreshRouteCacheScript($configFile, $warmupCommand);

            self::assertSame(0, $result['exitCode'], $result['stderr'] . $result['stdout']);
            self::assertStringContainsString('ApiAuthenticationContextMiddleware', (string) file_get_contents($cacheFile));
            self::assertStringNotContainsString('ApiTokenAuthenticationMiddleware', (string) file_get_contents($cacheFile));
        } finally {
            @unlink($cacheFile);
            @unlink($configFile);
        }
    }

    #[Test]
    public function itRemovesTheStaleRadixRouterCacheTogetherWithTheRoutingAttributesCache(): void
    {
        $routingAttributesCacheFile = tempnam(sys_get_temp_dir(), 'packio-routing-attributes-cache-');
        $radixCacheFile             = tempnam(sys_get_temp_dir(), 'packio-radix-cache-');
        $configFile                 = tempnam(sys_get_temp_dir(), 'packio-route-cache-config-');
        self::assertIsString($routingAttributesCacheFile);
        self::assertIsString($radixCacheFile);
        self::assertIsString($configFile);

        try {
            file_put_contents(
                $routingAttributesCacheFile,
                '<?php return [\'stale\' => \'routing-attributes\'];',
            );
            file_put_contents($radixCacheFile, '<?php return [\'stale\' => \'radix\'];');
            file_put_contents($configFile, '<?php return ' . var_export([
                'routing_attributes' => [
                    'cache' => [
                        'enabled' => true,
                        'file'    => $routingAttributesCacheFile,
                    ],
                ],
                'router'             => [
                    'radix' => [
                        'cache_enabled' => true,
                        'cache_file'    => $radixCacheFile,
                    ],
                ],
            ], true) . ';');

            $warmupCommand = sprintf(
                '%s -r %s %s',
                escapeshellarg(PHP_BINARY),
                escapeshellarg('file_put_contents($argv[1], "<?php return [\'fresh\' => true];");'),
                escapeshellarg($routingAttributesCacheFile),
            );
            $result = $this->runRefreshRouteCacheScript($configFile, $warmupCommand);

            self::assertSame(0, $result['exitCode'], $result['stderr'] . $result['stdout']);
            self::assertStringContainsString('fresh', (string) file_get_contents($routingAttributesCacheFile));
            self::assertFileDoesNotExist($radixCacheFile);
        } finally {
            @unlink($routingAttributesCacheFile);
            @unlink($radixCacheFile);
            @unlink($configFile);
        }
    }

    #[Test]
    public function itKeepsWorkingWhenOnlyTheRadixCacheIsConfigured(): void
    {
        $radixCacheFile = tempnam(sys_get_temp_dir(), 'packio-radix-cache-');
        $configFile     = tempnam(sys_get_temp_dir(), 'packio-route-cache-config-');
        self::assertIsString($radixCacheFile);
        self::assertIsString($configFile);

        try {
            file_put_contents($radixCacheFile, '<?php return [\'stale\' => \'radix\'];');
            file_put_contents($configFile, '<?php return ' . var_export([
                'router'             => [
                    'radix' => [
                        'cache_enabled' => true,
                        'cache_file'    => $radixCacheFile,
                    ],
                ],
            ], true) . ';');

            $failingWarmupCommand = sprintf(
                '%s -r %s',
                escapeshellarg(PHP_BINARY),
                escapeshellarg('exit(1);'),
            );
            $result = $this->runRefreshRouteCacheScript($configFile, $failingWarmupCommand);

            self::assertSame(0, $result['exitCode'], $result['stderr'] . $result['stdout']);
            self::assertStringNotContainsString('warmup', $result['stdout']);
            self::assertStringContainsString('no routing attributes cache to warm up', $result['stdout']);
            self::assertFileDoesNotExist($radixCacheFile);
        } finally {
            @unlink($radixCacheFile);
            @unlink($configFile);
        }
    }

    /**
     * @return array{exitCode: int, stderr: string, stdout: string}
     */
    private function runRefreshRouteCacheScript(string $configFile, string $warmupCommand): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $environment                                       = getenv();
        $environment['APP_ENV']                            = 'test';
        $environment['PACKIO_ROUTE_CACHE_CONFIG']          = $configFile;
        $environment['PACKIO_ROUTE_CACHE_WARMUP_COMMAND']  = $warmupCommand;
        $process                                           = proc_open(
            sprintf('%s bin/refresh-route-cache.php', escapeshellarg(PHP_BINARY)),
            $descriptors,
            $pipes,
            dirname(__DIR__, 4),
            $environment,
        );
        self::assertTrue(is_resource($process));

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stderr'   => $stderr,
            'stdout'   => $stdout,
        ];
    }
}
