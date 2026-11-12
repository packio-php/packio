<?php

declare(strict_types=1);

namespace PackioTest\Feature\Operations;

use Cycle\Database\Config\Postgres\TcpConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Cycle\Database\DatabaseProviderInterface;
use Mezzio\Router\RouteResult;
use Packio\Admin\Api\RuntimeSettingKeysApiFacadeInterface;
use Packio\Application\Infrastructure\Handler\ReadyHandler;
use Packio\Application\Infrastructure\Readiness\ReadinessChecker;
use Packio\Application\Middleware\OptionalRouteAuthenticationMiddleware;
use Packio\Registry\Infrastructure\Facade\ArchiveStorageApiFacade;
use PackioTest\Support\Base\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;

use function array_find;
use function array_splice;
use function basename;
use function bin2hex;
use function chdir;
use function chmod;
use function explode;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function glob;
use function hrtime;
use function in_array;
use function mkdir;
use function posix_geteuid;
use function random_bytes;
use function rmdir;
use function scandir;
use function stream_socket_get_name;
use function stream_socket_server;
use function strrpos;
use function substr;
use function sys_get_temp_dir;
use function unlink;

final class ReadinessFeatureTest extends FeatureTestCase
{
    private const int RELAXED_PROBE_BUDGET    = 10_000_000_000;
    private const int PRODUCTION_PROBE_BUDGET = 1_800_000_000;

    private string $archivePath;

    protected function setUp(): void
    {
        $this->archivePath = sys_get_temp_dir() . '/packio-ready-' . bin2hex(random_bytes(12));
        mkdir($this->archivePath, 0o700);
        $this->serviceOverrides['config']['storage']['archive']['local']['path'] = $this->archivePath;
        parent::setUp();

        // Production bootstraps the ORM before serving requests; the base test fixture only migrates.
        $this->getService('orm');
        $config = $this->getService('config');
        self::assertFileExists($config['cycle']['schema']['compiled']['path']);
        $database = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $database);
        foreach ($this->getService(RuntimeSettingKeysApiFacadeInterface::class)->settingKeys() as $key) {
            $database->database()->insert('project_settings')->values([
                'setting_key'   => $key,
                'setting_value' => 'inherit',
            ])->run();
        }

        $this->replaceReadyHandler($config);
    }

    protected function tearDown(): void
    {
        chmod($this->archivePath, 0o700);
        rmdir($this->archivePath);
        parent::tearDown();
    }

    #[Test]
    public function readyRouteReturnsOnlyThePublicReadyStatus(): void
    {
        $testResponse = $this->get('/ready')
            ->assertStatus(200)
            ->assertHeader('Cache-Control', 'no-store')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ;
        self::assertSame('{"status":"ready"}', $testResponse->body());
    }

    #[Test]
    public function checkerUsesProjectRootWhenParentWorkingDirectoryChanges(): void
    {
        $this->get('/ready')->assertStatus(200);
        $config                                            = $this->getService('config');
        $config['cycle']['migrator']['directory']          = 'data/db/cycle/migrations';
        $readinessChecker                                  = $this->checker($config);
        self::assertTrue($readinessChecker->isReady());
        $originalCwd = getcwd();
        self::assertIsString($originalCwd);

        try {
            chdir($this->archivePath);
            self::assertTrue($readinessChecker->isReady());
        } finally {
            chdir($originalCwd);
        }
    }

    #[Test]
    public function s3StorageIsReadyWithoutLocalArchiveDirectoryOrNetworkCalls(): void
    {
        $config                                        = $this->s3ReadinessConfig();
        $config['storage']['archive']['local']['path'] = $this->archivePath . '/missing';
        $this->replaceReadyHandler($config);

        self::assertSame('{"status":"ready"}', $this->get('/ready')->assertStatus(200)->body());
        self::assertDirectoryDoesNotExist($config['storage']['archive']['local']['path']);
    }

    #[Test]
    public function s3StorageWithInvalidConfigurationIsNotReady(): void
    {
        foreach (['bucket', 'endpoint', 'access_key', 'secret_key'] as $key) {
            $config                                   = $this->s3ReadinessConfig();
            $config['storage']['archive']['s3'][$key] = '';
            $this->replaceReadyHandler($config);

            self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
        }
    }

    #[Test]
    public function s3StorageStillRequiresRuntimeSettings(): void
    {
        $this->replaceReadyHandler($this->s3ReadinessConfig());
        $this->get('/ready')->assertStatus(200);
        $this->getService('dbal')->database()->delete('project_settings', [
            'setting_key' => 'oauth.github.enabled',
        ])->run();
        $this->replaceReadyHandler($this->s3ReadinessConfig());

        self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
    }

    #[Test]
    public function readinessResultIsCachedForOneSecond(): void
    {
        $readinessChecker = $this->replaceReadyHandler($this->s3ReadinessConfig());
        $this->get('/ready')->assertStatus(200);
        $database = $this->getService('dbal')->database();
        $database->delete('project_settings', [
            'setting_key' => 'oauth.github.enabled',
        ])->run();
        $this->setCachedAt($readinessChecker, hrtime(true) - 500_000_000);
        $this->get('/ready')->assertStatus(200);

        $this->setCachedAt($readinessChecker, hrtime(true) - 1_500_000_000);
        $this->get('/ready')->assertStatus(503);
        $database->insert('project_settings')->values([
            'setting_key'   => 'oauth.github.enabled',
            'setting_value' => 'inherit',
        ])->run();
        $this->setCachedAt($readinessChecker, hrtime(true) - 500_000_000);
        $this->get('/ready')->assertStatus(503);

        $this->setCachedAt($readinessChecker, hrtime(true) - 1_500_000_000);
        $this->get('/ready')->assertStatus(200);
    }

    #[Test]
    public function unsupportedArchiveDriverIsNotReady(): void
    {
        $config                                 = $this->getService('config');
        $config['storage']['archive']['driver'] = 'unsupported';
        self::assertFalse($this->checker($config)->isReady());
        $this->replaceReadyHandler($config);

        self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
    }

    #[Test]
    public function invalidPayloadEncodingIsNotReadyWithoutDisclosingConfig(): void
    {
        $this->get('/ready')->assertStatus(200);
        $config                               = $this->getService('config');
        $config['object_mapper']['cache_dir'] = "\xB1";
        self::assertFalse($this->checker($config)->isReady());
        $this->replaceReadyHandler($config);

        $testResponse = $this->get('/ready')
            ->assertStatus(503)
            ->assertHeader('Cache-Control', 'no-store')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ;
        self::assertSame('{"status":"not_ready"}', $testResponse->body());
        self::assertStringNotContainsString("\xB1", $testResponse->body());
    }

    #[Test]
    public function missingBootstrapStateIsNotReadyWithoutWarmup(): void
    {
        $this->get('/ready')->assertStatus(200);
        $config   = $this->getService('config');
        $path     = $config['cycle']['schema']['compiled']['path'];
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        unlink($path);
        $this->replaceReadyHandler($this->getService('config'));

        try {
            self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
            self::assertFileDoesNotExist($path, 'Readiness must not rebuild bootstrap state.');
        } finally {
            file_put_contents($path, $contents);
        }
    }

    #[Test]
    public function incompleteRuntimeSettingsBootstrapIsNotReady(): void
    {
        $this->get('/ready')->assertStatus(200);
        $this->getService('dbal')->database()->delete('project_settings', [
            'setting_key' => 'oauth.github.enabled',
        ])->run();
        $this->replaceReadyHandler($this->getService('config'));
        self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
    }

    #[Test]
    public function unwritableArchiveIsNotReadyAndProbeDoesNotWrite(): void
    {
        $this->skipWhenRootBypassesPermissions();
        $this->get('/ready')->assertStatus(200);
        chmod($this->archivePath, 0o500);
        $this->replaceReadyHandler($this->getService('config'));
        self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
        self::assertSame(['.', '..'], scandir($this->archivePath));
    }

    #[Test]
    public function archiveWithoutSearchPermissionIsNotReady(): void
    {
        $this->skipWhenRootBypassesPermissions();
        $this->get('/ready')->assertStatus(200);
        chmod($this->archivePath, 0o600);
        $this->replaceReadyHandler($this->getService('config'));
        self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
    }

    #[Test]
    public function cacheDirectoryAndLazyParentWithoutSearchPermissionAreNotReady(): void
    {
        $this->skipWhenRootBypassesPermissions();
        $directory = $this->archivePath . '/cache';
        mkdir($directory, 0o700);
        $config = $this->getService('config');

        try {
            foreach ([$directory, $directory . '/lazy-cache'] as $path) {
                chmod($directory, 0o700);
                $config['object_mapper']['cache_dir'] = $path;
                $this->replaceReadyHandler($config);
                $this->get('/ready')->assertStatus(200);
                chmod($directory, 0o600);
                $this->replaceReadyHandler($config);
                self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
            }
        } finally {
            chmod($directory, 0o700);
            self::assertSame(['.', '..'], scandir($directory), 'Readiness must not create lazy cache paths.');
            rmdir($directory);
        }
    }

    #[Test]
    public function unavailableDatabaseIsNotReadyWhileHealthStaysAlive(): void
    {
        $this->get('/ready')->assertStatus(200);
        $config                                                  = $this->getService('config');
        $config['cycle']['db-config']['connections']['postgres'] = new PostgresDriverConfig(
            connection: new TcpConnectionConfig(database: 'unavailable', host: '127.0.0.1', port: 1, user: 'unavailable', password: 'must-not-leak'),
        );
        $this->replaceReadyHandler($config, self::PRODUCTION_PROBE_BUDGET);
        $started      = hrtime(true);
        $testResponse = $this->get('/ready')
            ->assertStatus(503)
            ->assertHeader('Cache-Control', 'no-store')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ;
        self::assertSame('{"status":"not_ready"}', $testResponse->body());
        self::assertStringNotContainsString('must-not-leak', $testResponse->body());
        self::assertLessThan(2.0, (hrtime(true) - $started) / 1e9);
        $head = $this->request('HEAD', '/ready')
            ->assertStatus(503)
            ->assertHeader('Cache-Control', 'no-store')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ;
        self::assertSame('', $head->body());
        self::assertSame('{"status":"OK"}', $this->get('/health')->assertStatus(200)->body());
    }

    #[Test]
    public function stalledDatabaseWireIsTerminatedWithinTheTotalBudget(): void
    {
        $this->get('/ready')->assertStatus(200);
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($listener);
        $address = stream_socket_get_name($listener, false);
        self::assertIsString($address);
        $port                                                    = (int) substr($address, strrpos($address, ':') + 1);
        if ($port < 1) {
            self::fail('The local stalled-wire listener must expose a positive port.');
        }
        $config                                                  = $this->getService('config');
        $config['cycle']['db-config']['connections']['postgres'] = new PostgresDriverConfig(
            connection: new TcpConnectionConfig(database: 'blocked', host: '127.0.0.1', port: $port),
        );
        $this->replaceReadyHandler($config, self::PRODUCTION_PROBE_BUDGET);

        try {
            $started = hrtime(true);
            self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
            self::assertLessThan(2.0, (hrtime(true) - $started) / 1e9);
        } finally {
            fclose($listener);
        }
    }

    #[Test]
    public function operationalDispatchPreservesHeadExactPathsAndRegistryHostRestriction(): void
    {
        foreach ([
            '/health' => '{"status":"OK"}',
            '/ready'  => '{"status":"ready"}',
        ] as $path => $body) {
            foreach ([$path, $path . '?check=1'] as $requestPath) {
                foreach (['GET', 'HEAD'] as $method) {
                    $response = $this->request($method, $requestPath);
                    $response->assertStatus(200)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
                    self::assertSame('HEAD' === $method ? '' : $body, $response->body());
                    self::assertSame('', $response->header('Set-Cookie'), 'Probe requests must not start a session.');
                    if ('/ready' === $path) {
                        $response->assertHeader('Cache-Control', 'no-store');
                    }
                }
            }
        }

        $this->get('/health/unrelated')->assertStatus(404);
        $this->get('/ready/unrelated')->assertStatus(404);

        foreach (['/health', '/ready'] as $path) {
            foreach (['GET', 'HEAD'] as $method) {
                foreach ([false, true] as $inertia) {
                    $this->withHost('tenant.packio.us');
                    $headers = $inertia ? [
                        'X-Inertia' => 'true',
                    ] : [];
                    $response = $this->request($method, $path . '?check=1', [], $headers);
                    $target   = 'https://packio.us' . $path . '?check=1';
                    if ($inertia) {
                        $response->assertStatus(409)->assertHeader('X-Inertia-Location', $target);
                    } else {
                        $response->assertStatus(302)->assertHeader('Location', $target);
                    }
                }
            }
        }
    }

    #[Test]
    public function operationalProbeMethodsPreserveCorsAndCsrfOrdering(): void
    {
        $allowHeaders = 'Accept, Authorization, Content-Type, X-API-TOKEN, X-Inertia, X-Inertia-Version, X-Requested-With';
        foreach (['/health', '/ready', '/api/v1/health'] as $path) {
            $this->request('OPTIONS', $path, [], [
                'Access-Control-Request-Method' => 'POST',
                'Origin'                        => 'https://packio.us',
            ])
                ->assertStatus(204)
                ->assertHeader('Access-Control-Allow-Origin', 'https://packio.us')
                ->assertHeader('Access-Control-Allow-Methods', 'GET')
                ->assertHeader('Access-Control-Allow-Headers', $allowHeaders)
            ;
        }

        foreach (['/health', '/ready'] as $path) {
            $withOrigin = $this->request('POST', $path, [], [
                'Origin' => 'https://packio.us',
            ]);
            $withOrigin->assertStatus(405)->assertHeader('Allow', 'GET,HEAD');
            $withOrigin->assertHeader('Access-Control-Allow-Origin', 'https://packio.us');
            self::assertSame('', $withOrigin->body());

            $withoutOrigin = $this->request('POST', $path);
            $withoutOrigin->assertStatus(405)->assertHeader('Allow', 'GET,HEAD');
            self::assertSame('', $withoutOrigin->body());
        }

        foreach (['/health', '/ready'] as $path) {
            $withCsrf = $this->withCsrf()->post($path, [], [
                'Origin' => 'https://packio.us',
            ]);
            $withCsrf->assertStatus(405)->assertHeader('Allow', 'GET,HEAD');
            $withCsrf->assertHeader('Access-Control-Allow-Origin', 'https://packio.us');
            self::assertSame('', $withCsrf->body());
        }
    }

    #[Test]
    public function implicitHeadPreservesOrdinaryUiAndApiStatusAndAuthorization(): void
    {
        $readinessAuthBoundary                                                                                                   = new ReadinessAuthBoundary();
        $this->serviceOverrides['config']['dependencies']['delegators'][OptionalRouteAuthenticationMiddleware::class][]          = static function(
            $container,
            string $name,
            callable $callback,
        ) use ($readinessAuthBoundary): MiddlewareInterface {
            $middleware = $callback();

            return new readonly class($middleware, $readinessAuthBoundary) implements MiddlewareInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private ReadinessAuthBoundary $readinessAuthBoundary,
                ) {}

                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    $routeResult                                 = $request->getAttribute(RouteResult::class);
                    $this->readinessAuthBoundary->observations[] = [
                        'method'         => $request->getMethod(),
                        'routeName'      => $routeResult instanceof RouteResult && $routeResult->isSuccess()
                            ? $routeResult->getMatchedRouteName()
                            : null,
                        'hasRouteResult' => $routeResult instanceof RouteResult,
                    ];

                    return $this->middleware->process($request, $handler);
                }
            };
        };
        $this->rebuildApplication();

        foreach ([
            ['/auth/login', 200, 'auth.login', 'text/html; charset=UTF-8', 'no-store, no-cache, must-revalidate', 'no-cache, must-revalidate', ''],
            ['/admin', 302, 'admin.dashboard', '', '', '', '/auth/login'],
            ['/api/v1/health', 200, 'api.v1.health', 'application/json', '', '', ''],
            ['/api/v1/admin/users', 401, 'api.v1.admin.users.index', 'application/json', '', '', ''],
            ['/account/banned', 302, 'account.banned', '', '', '', '/auth/login'],
        ] as [$path, $expectedStatus, $expectedRouteName, $expectedContentType, $getCacheControl, $headCacheControl, $location]) {
            $get  = $this->get($path)->assertStatus($expectedStatus);
            $head = $this->request('HEAD', $path)->assertStatus($expectedStatus);
            if ('/auth/login' === $path) {
                self::assertStringContainsString('<html', $head->body(), 'The original login HEAD response retains its HTML body.');
            } else {
                self::assertSame($get->body(), $head->body(), 'Raw Radix HEAD preserves the original response body for ' . $path);
            }
            if ('' !== $expectedContentType) {
                self::assertNotSame('', $head->body(), 'The original pipeline keeps the handler body for ' . $path);
            }
            self::assertSame(
                [
                    [
                        'method'         => 'GET',
                        'routeName'      => $expectedRouteName,
                        'hasRouteResult' => true,
                    ],
                    [
                        'method'         => 'HEAD',
                        'routeName'      => $expectedRouteName,
                        'hasRouteResult' => true,
                    ],
                ],
                array_splice($readinessAuthBoundary->observations, 0, 2),
                'Routing must preserve the original method and RouteResult through the optional-auth boundary for ' . $path,
            );
            self::assertSame($location, $get->header('Location'));
            self::assertSame($location, $head->header('Location'));
            self::assertSame($expectedContentType, $get->header('Content-Type'));
            self::assertSame($expectedContentType, $head->header('Content-Type'));
            self::assertSame($getCacheControl, $get->header('Cache-Control'));
            self::assertSame($headCacheControl, $head->header('Cache-Control'));
            self::assertSame('', $get->header('Allow'));
            self::assertSame('', $head->header('Allow'));
            self::assertSame('', $get->header('Access-Control-Allow-Origin'));
            self::assertSame('', $head->header('Access-Control-Allow-Origin'));
            self::assertSame('', $get->header('Access-Control-Allow-Methods'));
            self::assertSame('', $head->header('Access-Control-Allow-Methods'));
        }
    }

    #[Test]
    public function missingMappedDatabaseTableIsNotReadyEvenWithAnExecutedLedger(): void
    {
        $this->get('/ready')->assertStatus(200);
        $provider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $provider);
        $database = $provider->database();
        $database->query('ALTER TABLE jobs RENAME TO o1_missing_jobs');
        $this->replaceReadyHandler($this->getService('config'));

        try {
            self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
        } finally {
            $database->query('ALTER TABLE o1_missing_jobs RENAME TO jobs');
        }
    }

    #[Test]
    public function missingExecutedMigrationIsNotReady(): void
    {
        $this->get('/ready')->assertStatus(200);
        $config = $this->getService('config');
        $files  = glob($config['cycle']['migrator']['directory'] . '/*.php');
        self::assertIsArray($files);
        $installed = [];
        foreach ($files as $file) {
            $installed[] = explode('_', basename($file, '.php'), 3)[2] ?? '';
        }

        $provider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $provider);
        $database = $provider->database();
        $driver   = $database->getDriver();
        self::assertSame(0, $driver->getTransactionLevel());
        $table     = $config['cycle']['migrator']['table'];
        $rows      = $driver->query('SELECT id, migration, time_executed, created_at FROM "' . $table . '" WHERE time_executed IS NOT NULL')->fetchAll();
        $ledgerRow = array_find($rows, fn ($row) => in_array($row['migration'], $installed, true));
        self::assertIsArray($ledgerRow, 'Expected an executed migration matching an installed migration file.');
        self::assertSame(0, $driver->getTransactionLevel());
        $deletedRows = 0;

        try {
            $deletedRows = $driver->execute('DELETE FROM "' . $table . '" WHERE id = ?', [$ledgerRow['id']]);
            self::assertSame(1, $deletedRows);
            $this->replaceReadyHandler($this->getService('config'));
            self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
        } finally {
            if ($deletedRows > 0) {
                $driver->query(
                    'INSERT INTO "' . $table . '" (id, migration, time_executed, created_at) VALUES (?, ?, ?, ?)',
                    [$ledgerRow['id'], $ledgerRow['migration'], $ledgerRow['time_executed'], $ledgerRow['created_at']],
                );
            }
        }

        $this->replaceReadyHandler($this->getService('config'));
        self::assertSame('{"status":"ready"}', $this->get('/ready')->assertStatus(200)->body());
    }

    #[Test]
    public function missingMappedDatabaseColumnIsNotReadyEvenWithAnExecutedLedger(): void
    {
        $this->get('/ready')->assertStatus(200);
        $provider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $provider);
        $database = $provider->database();
        $driver   = $database->getDriver();
        self::assertSame(0, $driver->getTransactionLevel());
        $driver->execute('ALTER TABLE jobs RENAME COLUMN payload TO readiness_missing_payload');
        $this->replaceReadyHandler($this->getService('config'));

        try {
            self::assertSame('{"status":"not_ready"}', $this->get('/ready')->assertStatus(503)->body());
        } finally {
            $driver->execute('ALTER TABLE jobs RENAME COLUMN readiness_missing_payload TO payload');
        }

        $this->replaceReadyHandler($this->getService('config'));
        self::assertSame('{"status":"ready"}', $this->get('/ready')->assertStatus(200)->body());
    }

    #[Test]
    public function readinessPreservesTheExistingConnectionTransaction(): void
    {
        $this->get('/ready')->assertStatus(200);
        $provider = $this->getService('dbal');
        self::assertInstanceOf(DatabaseProviderInterface::class, $provider);
        $database = $provider->database();
        $database->begin();

        try {
            $database->insert('project_settings')->values([
                'setting_key'   => 'o1.transaction',
                'setting_value' => 'inherit',
            ])->run();
            $this->get('/ready')->assertStatus(200);
            self::assertSame(1, $database->getDriver()->getTransactionLevel());
            self::assertSame(1, $database->select()->from('project_settings')->where('setting_key', 'o1.transaction')->count());
        } finally {
            $database->rollback();
        }
        self::assertSame(0, $database->select()->from('project_settings')->where('setting_key', 'o1.transaction')->count());
    }

    /** @return array<string, mixed> */
    private function s3ReadinessConfig(): array
    {
        $config                                 = $this->getService('config');
        $config['storage']['archive']['driver'] = 's3';
        $config['storage']['archive']['s3']     = [
            'bucket'     => 'readiness-test',
            'endpoint'   => 'https://127.0.0.1:1',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret',
        ];

        return $config;
    }

    private function skipWhenRootBypassesPermissions(): void
    {
        if (0 === posix_geteuid()) {
            self::markTestSkipped('Root bypasses filesystem permissions; run this permission check as a non-root user.');
        }
    }

    /** @param array<string, mixed> $config */
    private function replaceReadyHandler(array $config, int $probeBudgetNanoseconds = self::RELAXED_PROBE_BUDGET): ReadinessChecker
    {
        // Attribute route middleware memoizes the first resolved handler instance.
        // Inject the replacement before rebuilding the application and its route pipeline.
        $readinessChecker                            = $this->checker($config, $probeBudgetNanoseconds);
        $this->serviceOverrides[ReadyHandler::class] = new ReadyHandler($readinessChecker);
        $this->rebuildApplication();
        $this->application();

        return $readinessChecker;
    }

    private function setCachedAt(ReadinessChecker $readinessChecker, int $at): void
    {
        new ReflectionProperty($readinessChecker, 'cachedAt')->setValue($readinessChecker, $at);
    }

    /** @param array<string, mixed> $config */
    private function checker(array $config, int $probeBudgetNanoseconds = self::RELAXED_PROBE_BUDGET): ReadinessChecker
    {
        return new ReadinessChecker(
            $config,
            $this->getService(RuntimeSettingKeysApiFacadeInterface::class),
            new ArchiveStorageApiFacade($config),
            $probeBudgetNanoseconds,
        );
    }
}
