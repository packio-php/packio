<?php

declare(strict_types=1);

namespace PackioTest\Support\Base;

use const DIRECTORY_SEPARATOR;
use const PHP_SESSION_ACTIVE;

use Cycle\Database\Config\Postgres\DsnConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Exception;
use Laminas\ServiceManager\ServiceManager;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Sirix\Cycle\Service\MigratorService;
use Sirix\Mezzio\Router\Enum\CacheConfig;
use Throwable;

use function array_replace_recursive;
use function bin2hex;
use function chdir;
use function dirname;
use function fwrite;
use function getcwd;
use function getenv;
use function getmypid;
use function hash;
use function implode;
use function ini_set;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function ksort;
use function microtime;
use function mkdir;
use function putenv;
use function random_bytes;
use function register_shutdown_function;
use function rmdir;
use function scandir;
use function serialize;
use function session_id;
use function session_status;
use function session_write_close;
use function sprintf;
use function str_replace;
use function sys_get_temp_dir;
use function unlink;

/**
 * Base test case for integration tests.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected ContainerInterface $container;

    /** @var array<string, mixed> */
    protected array $serviceOverrides = [];

    private string $cycleSchemaPath;
    private string $sessionPath;
    private string $rateLimitPath;
    private string $cachePath;
    private bool $routingAttributesCacheEnabled = false;

    /** @var null|array<string, mixed> */
    private static ?array $baseConfig = null;

    private static ?string $baseConfigEnvironmentFingerprint = null;

    /** @var null|non-empty-string */
    private static ?string $sharedPostgresSchema = null;

    private static ?int $sharedPostgresSchemaOwnerProcessId = null;

    private static ?string $sharedCycleSchemaPath = null;

    private static bool $migrationsApplied = false;

    private static bool $postgresCleanupPrepared = false;

    private static ?string $postgresTruncateStatement = null;

    private static ?PDO $cleanupPostgresPdo = null;

    private static ?int $cleanupPostgresPdoOwnerProcessId = null;

    private static float $postgresCleanupSeconds = 0.0;

    private static int $postgresCleanupCount = 0;

    private static ?string $sharedTemporaryRoot = null;

    private static ?int $sharedTemporaryRootOwnerProcessId = null;

    private static int $temporaryTestCounter = 0;

    private static bool $shutdownCleanupRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->closeActiveSession();
        session_id('');
        $_COOKIE  = [];
        $_SESSION = [];
        $this->prepareTemporaryPaths();

        try {
            $this->container = $this->buildContainer();
            $this->runMigrations();
        } catch (Throwable $exception) {
            try {
                $this->cleanupTemporaryState();
            } catch (Throwable $cleanupException) {
                throw new RuntimeException(
                    'The PostgreSQL test schema could not be cleaned up after setup failed.',
                    0,
                    $cleanupException,
                );
            }

            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        $this->closeActiveSession();

        try {
            parent::tearDown();
        } finally {
            $this->cleanupTemporaryState();
        }
    }

    protected function getService(string $id): mixed
    {
        if (! $this->container->has($id)) {
            throw new RuntimeException('Service ' . $id . ' is not configured in test container');
        }

        return $this->container->get($id);
    }

    protected function enableRoutingAttributesCache(): void
    {
        $this->routingAttributesCacheEnabled = true;
    }

    protected function rebuildContainer(): void
    {
        $this->container = $this->buildContainer();
    }

    /**
     * Call before forking a test process so the child cannot inherit the cleanup PDO socket.
     */
    protected static function disconnectPostgresCleanupConnectionBeforeFork(): void
    {
        self::$cleanupPostgresPdo               = null;
        self::$cleanupPostgresPdoOwnerProcessId = null;
    }

    protected function routingAttributesCacheFile(): string
    {
        return $this->cachePath . '/mezzio-routing-attributes.php';
    }

    private function buildContainer(): ContainerInterface
    {
        $this->configurePostgresEnvironment();

        putenv('CYCLE_MIGRATIONS_DISABLED=0');
        putenv('APP_ENV=test');
        putenv('MAIL_DSN=null://null');
        putenv('APP_URL=https://packio.us');
        putenv('BRAND_DOMAIN=packio.us');
        putenv('BRAND_REGISTRY_BASE_DOMAIN=packio.us');
        putenv('CORS_ALLOWED_ORIGINS=https://packio.us');
        $_ENV['APP_ENV']                       = 'test';
        $_SERVER['APP_ENV']                    = 'test';
        $_ENV['MAIL_DSN']                      = 'null://null';
        $_SERVER['MAIL_DSN']                   = 'null://null';
        $_ENV['APP_URL']                       = 'https://packio.us';
        $_SERVER['APP_URL']                    = 'https://packio.us';
        $_ENV['BRAND_DOMAIN']                  = 'packio.us';
        $_SERVER['BRAND_DOMAIN']               = 'packio.us';
        $_ENV['BRAND_REGISTRY_BASE_DOMAIN']    = 'packio.us';
        $_SERVER['BRAND_REGISTRY_BASE_DOMAIN'] = 'packio.us';
        $_ENV['CORS_ALLOWED_ORIGINS']          = 'https://packio.us';
        $_SERVER['CORS_ALLOWED_ORIGINS']       = 'https://packio.us';

        $proxyCachePath = $this->cachePath . '/proxies';
        if (! is_dir($proxyCachePath)) {
            mkdir($proxyCachePath, 0o777, true);
        }

        $config = $this->baseConfig();

        $configOverride = $this->serviceOverrides['config'] ?? null;
        if (is_array($configOverride)) {
            $config = array_replace_recursive($config, $configOverride);
        }

        $config = array_replace_recursive($config, [
            'dependencies'         => [
                'lazy_services' => [
                    // Do not load generated proxies left by an older version of an interface.
                    'proxies_namespace'  => 'PackioTestGeneratedProxy',
                    'proxies_target_dir' => $proxyCachePath,
                    'write_proxy_files'  => false,
                ],
            ],
            'cycle'                => [
                'db-config' => $this->databaseConfig(),
                'schema'    => [
                    'compiled' => [
                        'path' => $this->cycleSchemaPath,
                    ],
                    'cache'    => [
                        'enabled' => true,
                    ],
                ],
            ],
            'security'             => [
                'rate_limits' => [
                    'storage_dir' => $this->rateLimitPath,
                ],
            ],
            'sirix_mezzio_valinor' => [
                'mapper' => [
                    'cache_dir' => $this->cachePath . '/valinor',
                ],
            ],
            'router'               => [
                'radix' => [
                    CacheConfig::Enabled->value => false,
                    CacheConfig::File->value    => null,
                ],
            ],
            'routing_attributes'   => [
                'cache' => [
                    'enabled' => $this->routingAttributesCacheEnabled,
                    'file'    => $this->routingAttributesCacheEnabled
                        ? $this->cachePath . '/mezzio-routing-attributes.php'
                        : null,
                ],
            ],
            'twig'                 => [
                'cache_dir' => false,
            ],
        ]);

        $dependencies = $config['dependencies'];
        if (! is_array($dependencies)) {
            throw new Exception('Test config does not contain service dependencies');
        }

        $dependencies['services']['config'] = $config;
        foreach ($this->serviceOverrides as $id => $service) {
            if ('config' === $id) {
                continue;
            }

            $dependencies['services'][$id] = $service;
            unset($dependencies['aliases'][$id], $dependencies['factories'][$id], $dependencies['invokables'][$id]);
        }

        return new ServiceManager($dependencies);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseConfig(): array
    {
        $environment = getenv();
        ksort($environment);
        $fingerprint = hash('sha256', serialize($environment));
        if (null !== self::$baseConfig && self::$baseConfigEnvironmentFingerprint === $fingerprint) {
            return self::$baseConfig;
        }

        $workingDirectory = getcwd();
        if (false === $workingDirectory) {
            throw new RuntimeException('Unable to determine the current working directory for test config loading.');
        }

        $configCacheDirectory = sys_get_temp_dir() . '/packio-test-config-' . getmypid() . '-' . bin2hex(random_bytes(16));
        mkdir($configCacheDirectory, 0o700, true);

        try {
            if (! chdir($configCacheDirectory)) {
                throw new RuntimeException('Unable to isolate the ConfigAggregator cache for tests.');
            }

            /** @var mixed $config */
            $config = require dirname(__DIR__, 3) . '/config/config.php';
        } finally {
            $restoredWorkingDirectory = chdir($workingDirectory);
            self::removeDirectory($configCacheDirectory);

            if (! $restoredWorkingDirectory) {
                throw new RuntimeException('Unable to restore the test working directory after config loading.');
            }
        }

        if (! is_array($config)) {
            throw new Exception('Merged application config must be an array');
        }

        /** @var array<string, mixed> $config */
        self::$baseConfig                       = $config;
        self::$baseConfigEnvironmentFingerprint = $fingerprint;

        return $config;
    }

    private function runMigrations(): void
    {
        if (self::$migrationsApplied) {
            $this->preparePostgresCleanup();

            return;
        }

        if (! $this->container->has(MigratorService::class)) {
            return;
        }

        $migrator = $this->container->get(MigratorService::class);
        if (! $migrator instanceof MigratorService) {
            return;
        }

        $migrator->migrate(static function(string $message): void {
            // Keep output silent in tests.
            unset($message);
        });

        self::$migrationsApplied = true;
        $this->preparePostgresCleanup();
    }

    private function prepareTemporaryPaths(): void
    {
        $this->ensureSharedPostgresSchema();
        $this->ensureSharedTemporaryRoot();
        $temporaryRoot = self::$sharedTemporaryRoot ?? throw new RuntimeException('Temporary test root was not created.');
        $testPath      = $temporaryRoot . '/test-' . (++self::$temporaryTestCounter);

        $this->sessionPath     = $testPath . '/sessions';
        $this->rateLimitPath   = $testPath . '/rate-limits';
        $this->cachePath       = $testPath . '/cache';
        $this->cycleSchemaPath = self::$sharedCycleSchemaPath ?? throw new RuntimeException('PostgreSQL test Cycle schema path was not created.');

        if (! is_dir($this->sessionPath)) {
            mkdir($this->sessionPath, 0o777, true);
        }

        if (! is_dir($this->rateLimitPath)) {
            mkdir($this->rateLimitPath, 0o777, true);
        }

        if (! is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0o777, true);
        }

        ini_set('session.save_path', $this->sessionPath);
        ini_set('session.save_handler', 'files');
        ini_set('session.gc_maxlifetime', '7200');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }

    private function cleanupTemporaryState(): void
    {
        try {
            $this->truncateSharedPostgresSchema();
        } finally {
            if (is_dir($this->sessionPath)) {
                self::removeDirectory($this->sessionPath);
            }

            if (is_dir($this->rateLimitPath)) {
                self::removeDirectory($this->rateLimitPath);
            }

            if (is_dir($this->cachePath)) {
                self::removeDirectory($this->cachePath);
            }

            @rmdir(dirname($this->cachePath));
        }
    }

    private function closeActiveSession(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }

        $_SESSION = [];
    }

    private static function removeDirectory(string $path): void
    {
        $entries = scandir($path);
        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry) {
                continue;
            }

            if ('..' === $entry) {
                continue;
            }

            $target = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($target)) {
                self::removeDirectory($target);

                continue;
            }

            @unlink($target);
        }

        @rmdir($path);
    }

    private function ensureSharedTemporaryRoot(): void
    {
        $processId = getmypid();
        if (false === $processId) {
            throw new RuntimeException('Unable to determine the temporary test root owner process.');
        }

        if (self::$sharedTemporaryRootOwnerProcessId !== $processId) {
            self::$sharedTemporaryRoot                = null;
            self::$temporaryTestCounter               = 0;
            self::$sharedTemporaryRootOwnerProcessId  = $processId;
        }

        if (null !== self::$sharedTemporaryRoot) {
            return;
        }

        self::$sharedTemporaryRoot = sys_get_temp_dir() . '/packio-mezzio-test-' . $processId . '-' . bin2hex(random_bytes(16));
        mkdir(self::$sharedTemporaryRoot, 0o777, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseConfig(): array
    {
        return [
            'databases'   => [
                'default'               => [
                    'connection' => 'postgres',
                ],
                'source_deletion_lease' => [
                    'connection' => 'postgres_source_deletion_lease',
                ],
            ],
            'connections' => [
                'postgres'                       => new PostgresDriverConfig(
                    connection: new DsnConnectionConfig(
                        dsn: self::postgresDsn(),
                        user: self::postgresUser(),
                        password: self::postgresPassword(),
                    ),
                    schema: self::$sharedPostgresSchema ?? throw new RuntimeException('PostgreSQL test schema was not created.'),
                    reconnect: true,
                    timezone: 'UTC',
                    queryCache: false,
                ),
                'postgres_source_deletion_lease' => new PostgresDriverConfig(
                    connection: new DsnConnectionConfig(
                        dsn: self::postgresDsn(),
                        user: self::postgresUser(),
                        password: self::postgresPassword(),
                    ),
                    schema: self::$sharedPostgresSchema ?? throw new RuntimeException('PostgreSQL test schema was not created.'),
                    reconnect: false,
                    timezone: 'UTC',
                    queryCache: false,
                ),
            ],
        ];
    }

    private function configurePostgresEnvironment(): void
    {
        putenv('DEFAULT_DB_CONNECTION=postgres');
        $_ENV['DEFAULT_DB_CONNECTION']    = 'postgres';
        $_SERVER['DEFAULT_DB_CONNECTION'] = 'postgres';
    }

    private function ensureSharedPostgresSchema(): void
    {
        if (null !== self::$sharedPostgresSchema) {
            return;
        }

        $processId = getmypid();
        if (false === $processId) {
            throw new RuntimeException('Unable to determine the PostgreSQL test schema owner process.');
        }

        $schema = $this->newPostgresSchema();
        $this->createPostgresSchema($schema);

        self::$sharedPostgresSchema               = $schema;
        self::$sharedPostgresSchemaOwnerProcessId = $processId;
        self::$sharedCycleSchemaPath              = sys_get_temp_dir() . '/packio-mezzio-cycle-schema-' . $processId . '-' . bin2hex(random_bytes(16)) . '.php';

        if (self::$shutdownCleanupRegistered) {
            return;
        }

        register_shutdown_function(static function(): void {
            self::dropSharedPostgresSchemaOnShutdown();
        });
        self::$shutdownCleanupRegistered = true;
    }

    private function preparePostgresCleanup(): void
    {
        if (self::$postgresCleanupPrepared || null === self::$sharedPostgresSchema) {
            return;
        }

        try {
            $statement = self::cleanupPostgresPdo()->prepare(
                "SELECT tablename FROM pg_tables WHERE schemaname = :schema AND tablename <> 'migrations' ORDER BY tablename",
            );
            $statement->execute([
                'schema' => self::$sharedPostgresSchema,
            ]);

            $tables = [];
            foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $table) {
                if (! is_string($table)) {
                    continue;
                }

                if ('' === $table) {
                    continue;
                }

                $tables[] = self::quotePostgresIdentifier(self::$sharedPostgresSchema)
                    . '.'
                    . self::quotePostgresIdentifier($table);
            }

            self::$postgresTruncateStatement = [] === $tables
                ? null
                : 'TRUNCATE TABLE ' . implode(', ', $tables) . ' RESTART IDENTITY CASCADE';
            self::$postgresCleanupPrepared = true;
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to prepare shared PostgreSQL test schema cleanup.', 0, $exception);
        }
    }

    private function truncateSharedPostgresSchema(): void
    {
        if (! self::$migrationsApplied || null === self::$sharedPostgresSchema || ! self::$postgresCleanupPrepared) {
            return;
        }

        $startedAt = microtime(true);

        try {
            if (null === self::$postgresTruncateStatement) {
                return;
            }

            self::cleanupPostgresPdo()->exec(self::$postgresTruncateStatement);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to reset the shared PostgreSQL test schema.', 0, $exception);
        } finally {
            self::$postgresCleanupSeconds += microtime(true) - $startedAt;
            ++self::$postgresCleanupCount;
        }
    }

    private static function dropSharedPostgresSchemaOnShutdown(): void
    {
        if (null === self::$sharedPostgresSchema) {
            return;
        }

        if (self::$sharedPostgresSchemaOwnerProcessId !== getmypid()) {
            if (self::$sharedTemporaryRootOwnerProcessId === getmypid()
                && null !== self::$sharedTemporaryRoot
                && is_dir(self::$sharedTemporaryRoot)
            ) {
                self::removeDirectory(self::$sharedTemporaryRoot);
            }

            return;
        }

        try {
            self::dropPostgresSchema(self::$sharedPostgresSchema);
        } catch (Throwable) {
            // Shutdown cleanup is best effort; PHPUnit has already reported the original failure.
        } finally {
            if (null !== self::$sharedCycleSchemaPath && is_file(self::$sharedCycleSchemaPath)) {
                @unlink(self::$sharedCycleSchemaPath);
            }

            if (self::$sharedTemporaryRootOwnerProcessId === getmypid()
                && null !== self::$sharedTemporaryRoot
                && is_dir(self::$sharedTemporaryRoot)
            ) {
                self::removeDirectory(self::$sharedTemporaryRoot);
            }

            if ('ci' === getenv('PACKIO_TEST_RUNNER_MODE') && self::$postgresCleanupCount > 0) {
                fwrite(
                    STDERR,
                    sprintf(
                        "[test-timing] PostgreSQL cleanup: %d tests, %.3f s total, %.3f ms average\n",
                        self::$postgresCleanupCount,
                        self::$postgresCleanupSeconds,
                        self::$postgresCleanupSeconds / self::$postgresCleanupCount * 1000,
                    ),
                );
            }

            self::$sharedPostgresSchema               = null;
            self::$sharedPostgresSchemaOwnerProcessId = null;
            self::$sharedCycleSchemaPath              = null;
            self::$migrationsApplied                  = false;
            self::$postgresCleanupPrepared            = false;
            self::$postgresTruncateStatement          = null;
            self::$cleanupPostgresPdo                 = null;
            self::$cleanupPostgresPdoOwnerProcessId   = null;
            self::$sharedTemporaryRoot                = null;
            self::$sharedTemporaryRootOwnerProcessId  = null;
            self::$temporaryTestCounter               = 0;
        }
    }

    /**
     * @return non-empty-string
     */
    private function newPostgresSchema(): string
    {
        $processId = getmypid();

        return 'packio_test_' . (false === $processId ? 'unknown' : $processId) . '_' . bin2hex(random_bytes(16));
    }

    /**
     * @param non-empty-string $schema
     */
    private function createPostgresSchema(string $schema): void
    {
        try {
            self::cleanupPostgresPdo()->exec('CREATE SCHEMA ' . self::quotePostgresIdentifier($schema));
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf(
                    'Unable to create isolated PostgreSQL test schema "%s". Check the test database connection and schema privileges.',
                    $schema,
                ),
                0,
                $exception,
            );
        }
    }

    /**
     * @param non-empty-string $schema
     */
    private static function dropPostgresSchema(string $schema): void
    {
        try {
            self::cleanupPostgresPdo()->exec('DROP SCHEMA IF EXISTS ' . self::quotePostgresIdentifier($schema) . ' CASCADE');
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Unable to drop isolated PostgreSQL test schema "%s".', $schema),
                0,
                $exception,
            );
        }
    }

    /**
     * @return non-empty-string
     */
    private static function postgresDsn(): string
    {
        // Test data is disposable: no need to wait for a WAL flush on commit.
        return sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;options=-csynchronous_commit=off',
            self::postgresHost(),
            self::postgresPort(),
            self::postgresDatabaseName(),
        );
    }

    private static function cleanupPostgresPdo(): PDO
    {
        $processId = getmypid();
        if (false === $processId) {
            throw new RuntimeException('Unable to determine the PostgreSQL cleanup connection owner process.');
        }

        if (self::$cleanupPostgresPdoOwnerProcessId !== $processId) {
            // PDO connections must never be reused after a fork.
            self::$cleanupPostgresPdo               = null;
            self::$cleanupPostgresPdoOwnerProcessId = $processId;
        }

        if (self::$cleanupPostgresPdo instanceof PDO) {
            return self::$cleanupPostgresPdo;
        }

        try {
            $pdo = new PDO(
                self::postgresDsn(),
                self::postgresUser(),
                self::postgresPassword(),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ],
            );
            self::$cleanupPostgresPdo = $pdo;

            return $pdo;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Unable to connect to the isolated PostgreSQL test database. Start postgres-test or verify PACKIO_TEST_DATABASE_* variables.',
                0,
                $exception,
            );
        }
    }

    /**
     * @param non-empty-string $identifier
     *
     * @return non-empty-string
     */
    private static function quotePostgresIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * @return non-empty-string
     */
    private static function postgresHost(): string
    {
        $host = getenv('PACKIO_TEST_DATABASE_HOST');

        return false === $host || '' === $host ? '127.0.0.1' : $host;
    }

    /**
     * @return positive-int
     */
    private static function postgresPort(): int
    {
        $port = getenv('PACKIO_TEST_DATABASE_PORT');
        if (false === $port || '' === $port) {
            return 15433;
        }

        $normalizedPort = (int) $port;
        if ($normalizedPort < 1) {
            return 15433;
        }

        return $normalizedPort;
    }

    /**
     * @return non-empty-string
     */
    private static function postgresDatabaseName(): string
    {
        $database = getenv('PACKIO_TEST_DATABASE_NAME');

        return false === $database || '' === $database ? 'packio_test' : $database;
    }

    /**
     * @return non-empty-string
     */
    private static function postgresUser(): string
    {
        $user = getenv('PACKIO_TEST_DATABASE_USER');

        return false === $user || '' === $user ? 'packio_test' : $user;
    }

    /**
     * @return non-empty-string
     */
    private static function postgresPassword(): string
    {
        $password = getenv('PACKIO_TEST_DATABASE_PASS');

        return false === $password || '' === $password ? 'packio_test' : $password;
    }
}
