#!/bin/sh

set -eu

project_root=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd)
temporary_root=$(mktemp -d "${TMPDIR:-/tmp}/packio-bootstrap-test.XXXXXX")
trap 'rm -rf "$temporary_root"' EXIT HUP INT TERM

checkout="$temporary_root/checkout"
mkdir -p "$checkout/test/Support/Base" "$checkout/test/Support/Runner" "$checkout/data/cache" "$checkout/test/data/cache/phpunit" "$temporary_root/arbitrary-cwd" "$temporary_root/php-tmp"
cp -R "$project_root/config" "$checkout/config"
cp "$project_root/test/Support/bootstrap.php" "$checkout/test/Support/bootstrap.php"
cp "$project_root/test/Support/Base/IntegrationTestCase.php" "$checkout/test/Support/Base/IntegrationTestCase.php"
ln -s "$project_root/vendor" "$checkout/vendor"
printf '%s\n' '<?php return ["runtime_cache_sentinel" => "must not be read"];' > "$checkout/data/cache/config-cache.php"
printf '%s\n' 'runtime phpunit sentinel' > "$checkout/data/cache/.phpunit.cache"
printf '%s\n' 'test config sentinel' > "$checkout/test/data/cache/config-cache.php"
printf '%s\n' 'test phpunit sentinel' > "$checkout/test/data/cache/phpunit/cache-sentinel"

for sentinel in \
    "$checkout/data/cache/config-cache.php" \
    "$checkout/data/cache/.phpunit.cache" \
    "$checkout/test/data/cache/config-cache.php" \
    "$checkout/test/data/cache/phpunit/cache-sentinel"; do
    cp "$sentinel" "$sentinel.expected"
done

for working_directory in "$checkout" "$temporary_root/arbitrary-cwd"; do
    (
        cd "$working_directory"
        TMPDIR="$temporary_root/php-tmp" php -r '
            require $argv[1];
            if (getcwd() !== dirname($argv[1], 3)) {
                fwrite(STDERR, "Bootstrap did not select the checkout root as its working directory.\n");
                exit(1);
            }
            foreach (array_slice($argv, 3) as $sentinel) {
                if (! is_file($sentinel) || file_get_contents($sentinel) !== file_get_contents($sentinel . ".expected")) {
                    fwrite(STDERR, "Bootstrap altered a runtime or test sentinel: {$sentinel}\n");
                    exit(1);
                }
            }

            require $argv[2];
            $testCase = new class("testPlaceholder") extends PackioTest\Support\Base\IntegrationTestCase {
                public function testPlaceholder(): void {}
                protected function runTest(): void {}
            };
            $baseConfig = new ReflectionMethod($testCase, "baseConfig");
            $config = $baseConfig->invoke($testCase);
            if (! is_array($config) || array_key_exists("runtime_cache_sentinel", $config)) {
                fwrite(STDERR, "IntegrationTestCase loaded the runtime ConfigAggregator cache.\n");
                exit(1);
            }

            putenv("PACKIO_TEST_CONFIG_RELOAD=1");
            $_ENV["PACKIO_TEST_CONFIG_RELOAD"] = "1";
            $_SERVER["PACKIO_TEST_CONFIG_RELOAD"] = "1";
            $baseConfig->invoke($testCase);

            $ownedRoot = sys_get_temp_dir() . "/packio-shutdown-test-" . getmypid();
            mkdir($ownedRoot . "/cache", 0777, true);
            mkdir($ownedRoot . "/sessions", 0777, true);
            mkdir($ownedRoot . "/rate-limits", 0777, true);
            foreach ([
                "cycleSchemaPath" => $ownedRoot . "/cycle-schema.php",
                "sessionPath" => $ownedRoot . "/sessions",
                "rateLimitPath" => $ownedRoot . "/rate-limits",
                "cachePath" => $ownedRoot . "/cache",
            ] as $name => $value) {
                $property = new ReflectionProperty(PackioTest\Support\Base\IntegrationTestCase::class, $name);
                $property->setValue($testCase, $value);
            }
            $schema = new ReflectionProperty(PackioTest\Support\Base\IntegrationTestCase::class, "sharedPostgresSchema");
            $schema->setValue(null, "packio_test_proxy_config");
            // Keep the real shutdown PDO attempt local and fail-fast; this runner does not migrate or create a DB.
            putenv("PACKIO_TEST_DATABASE_HOST=127.0.0.1");
            putenv("PACKIO_TEST_DATABASE_PORT=1");
            putenv("PACKIO_TEST_DATABASE_NAME=packio_test");
            putenv("PACKIO_TEST_DATABASE_USER=packio_test");
            putenv("PACKIO_TEST_DATABASE_PASS=packio_test");
            $buildContainer = new ReflectionMethod($testCase, "buildContainer");
            $container = $buildContainer->invoke($testCase);
            try {
                $proxy = $container->get(Packio\Organization\Infrastructure\Facade\OrganizationApiFacade::class);
            } catch (Throwable $exception) {
                if (! str_contains($exception->getMessage(), "Provided directory \"data/cache/proxies\" does not exist")) {
                    throw $exception;
                }
                fwrite(STDERR, "ServiceManager could not create the lazy proxy from the isolated test cache: {$exception->getMessage()}\n");
                exit(1);
            }
            if (! $proxy instanceof ProxyManager\Proxy\VirtualProxyInterface
                || ! $proxy instanceof Packio\Organization\Api\OrganizationApiFacadeInterface
            ) {
                fwrite(STDERR, "ServiceManager did not create the configured lazy Organization API proxy.\n");
                exit(1);
            }

            $runtimeCache = $argv[3];
            if (file_get_contents($runtimeCache) !== file_get_contents($runtimeCache . ".expected")) {
                fwrite(STDERR, "IntegrationTestCase altered the runtime ConfigAggregator cache.\n");
                exit(1);
            }

            file_put_contents($ownedRoot . "/owned-test-artifact", "owned");
            $cycleSchema = $ownedRoot . "/cycle-schema.php";
            file_put_contents($cycleSchema, "<?php return [];\n");
            foreach ([
                "sharedPostgresSchema" => "packio_test_cleanup_probe",
                "sharedPostgresSchemaOwnerProcessId" => getmypid(),
                "sharedCycleSchemaPath" => $cycleSchema,
                "sharedTemporaryRoot" => $ownedRoot,
                "sharedTemporaryRootOwnerProcessId" => getmypid(),
            ] as $name => $value) {
                $property = new ReflectionProperty(PackioTest\Support\Base\IntegrationTestCase::class, $name);
                $property->setValue(null, $value);
            }
            (new ReflectionMethod(PackioTest\Support\Base\IntegrationTestCase::class, "dropSharedPostgresSchemaOnShutdown"))->invoke(null);
            if (is_dir($ownedRoot) || file_get_contents($runtimeCache) !== file_get_contents($runtimeCache . ".expected")) {
                fwrite(STDERR, "IntegrationTestCase shutdown did not preserve runtime data and remove only its test artifacts.\n");
                exit(1);
            }
            foreach (array_slice($argv, 3) as $sentinel) {
                if (! is_file($sentinel) || file_get_contents($sentinel) !== file_get_contents($sentinel . ".expected")) {
                    fwrite(STDERR, "IntegrationTestCase shutdown altered a runtime or test sentinel: {$sentinel}\n");
                    exit(1);
                }
            }
        ' "$checkout/test/Support/bootstrap.php" "$checkout/test/Support/Base/IntegrationTestCase.php" "$checkout/data/cache/config-cache.php" "$checkout/test/data/cache/config-cache.php" "$checkout/data/cache/.phpunit.cache" "$checkout/test/data/cache/phpunit/cache-sentinel"
    )

    if [ -n "$(find "$temporary_root/php-tmp" -mindepth 1 -print -quit)" ]; then
        printf 'IntegrationTestCase left temporary config or shutdown artifacts behind.\n' >&2
        exit 1
    fi
done
