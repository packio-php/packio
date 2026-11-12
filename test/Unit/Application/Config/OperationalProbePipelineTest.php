<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Config;

use Cycle\Database\Config\Postgres\TcpConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Application;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Mezzio\Helper\ServerUrlMiddleware;
use Mezzio\Helper\UrlHelperMiddleware;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\Middleware\ImplicitOptionsMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use Mezzio\Session\SessionMiddleware;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Application\Infrastructure\Handler\HealthHandler;
use Packio\Application\Infrastructure\Handler\ReadyHandler;
use Packio\Application\Infrastructure\Readiness\ReadinessProbe;
use Packio\Application\Middleware\ApiAuthenticationAttemptRateLimitMiddleware;
use Packio\Application\Middleware\CsrfValidationMiddleware;
use Packio\Application\Middleware\HandleAuthorizationExceptionMiddleware;
use Packio\Application\Middleware\HandleInertiaRequests;
use Packio\Application\Middleware\HtmlCacheControlMiddleware;
use Packio\Application\Middleware\OptionalRouteAuthenticationMiddleware;
use Packio\Application\Middleware\RefreshAuthenticatedSessionMiddleware;
use Packio\Application\Middleware\RequestSizeLimitMiddleware;
use Packio\Application\Middleware\RequireEmailVerificationMiddleware;
use Packio\Application\Middleware\RequireUnbannedUserMiddleware;
use Packio\Application\Middleware\ResolveCurrentMembershipMiddleware;
use Packio\Application\Middleware\ResolveCurrentOrganizationMiddleware;
use Packio\Application\Middleware\ResolveCurrentUserMiddleware;
use Packio\Application\Middleware\ResolveOrganizationActorContextMiddleware;
use Packio\Application\Middleware\RuntimeCorsMiddleware;
use Packio\Application\Middleware\StripPackageMappingErrorInertiaDecorationMiddleware;
use Packio\Application\Router\ImplicitHeadRouterAdapter;
use Packio\Application\Security\RateLimiter\FileRateLimiter;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Facade\OrganizationApiFacade;
use Packio\Organization\Infrastructure\Handler\OrganizationsHandler;
use Packio\Registry\Infrastructure\Middleware\ResolveRegistryOrganizationAttributeMiddleware;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;
use Sirix\Mezzio\Router\Enum\CacheConfig;
use Sirix\Mezzio\Router\RadixRouter;

use function bin2hex;
use function chdir;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function hash_file;
use function ini_get;
use function ini_set;
use function is_dir;
use function is_string;
use function microtime;
use function mkdir;
use function parse_url;
use function random_bytes;
use function rmdir;
use function scandir;
use function strlen;
use function strpos;
use function substr;
use function sys_get_temp_dir;
use function unlink;
use function var_export;

final class OperationalProbePipelineTest extends TestCase
{
    private string $root;
    private string $workingDirectory;
    private false|string $sessionSavePath;
    private false|string $sessionSaveHandler;
    private string $fixtureDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root               = dirname(__DIR__, 4);
        $this->workingDirectory   = getcwd() ?: $this->root;
        $this->sessionSavePath    = ini_get('session.save_path');
        $this->sessionSaveHandler = ini_get('session.save_handler');
        $this->fixtureDirectory   = sys_get_temp_dir() . '/packio-probes-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->fixtureDirectory, 0o700, true));
    }

    protected function tearDown(): void
    {
        chdir($this->workingDirectory);
        if (false !== $this->sessionSavePath) {
            ini_set('session.save_path', $this->sessionSavePath);
        }

        if (false !== $this->sessionSaveHandler) {
            ini_set('session.save_handler', $this->sessionSaveHandler);
        }
        $this->removeDirectory($this->fixtureDirectory);
        parent::tearDown();
    }

    #[Test]
    public function realApplicationDispatchesOperationalProbesWithoutResolvingMainStackOrDbServices(): void
    {
        $cacheRoot   = $this->fixtureDirectory . '/cache';
        $sessionRoot = $this->fixtureDirectory . '/sessions';
        $archiveRoot = $this->fixtureDirectory . '/archives';
        mkdir($cacheRoot, 0o700, true);
        mkdir($sessionRoot, 0o700, true);
        mkdir($archiveRoot, 0o700, true);
        ini_set('session.save_path', $sessionRoot);
        ini_set('session.save_handler', 'files');

        $discoveryConfig = $this->config(false, $cacheRoot, $sessionRoot, $archiveRoot);
        $this->assertReadinessFixtureReachesPostgres($discoveryConfig);
        $this->exerciseMatrix('discovery', $discoveryConfig, false);
        $this->assertNormalRoutingBoundary($discoveryConfig);

        $cachedConfig = $this->config(true, $cacheRoot, $sessionRoot, $archiveRoot);
        $this->seedRouteCaches($cachedConfig);
        self::assertFileExists($cachedConfig['routing_attributes']['cache']['file']);
        self::assertFileExists($cachedConfig['router']['radix'][CacheConfig::File->value]);
        $this->prependArtifactMarker($cachedConfig['routing_attributes']['cache']['file'], $cachedConfig['test_markers']['attributes']);
        $this->prependArtifactMarker($cachedConfig['router']['radix'][CacheConfig::File->value], $cachedConfig['test_markers']['radix']);
        $attributeCacheHash = hash_file('sha256', $cachedConfig['routing_attributes']['cache']['file']);
        $radixCacheHash     = hash_file('sha256', $cachedConfig['router']['radix'][CacheConfig::File->value]);
        $this->exerciseMatrix('compiled cache', $cachedConfig, true);
        $this->assertNormalRoutingBoundary($cachedConfig);

        foreach (['/health', '/ready'] as $path) {
            foreach (['GET', 'HEAD'] as $method) {
                foreach ([false, true] as $inertia) {
                    $container        = $this->application($cachedConfig);
                    $probeState       = $container['state'];
                    $requestPath      = $path . '?check=1';
                    $response         = $container['application']->handle($this->request($method, $requestPath, 'tenant.packio.us', inertia: $inertia));
                    self::assertSame($inertia ? 409 : 302, $response->getStatusCode(), 'Registry host must be restricted before probe dispatch.');
                    $target = 'https://packio.us' . $requestPath;
                    if ($inertia) {
                        self::assertSame($target, $response->getHeaderLine('X-Inertia-Location'));
                    } else {
                        self::assertSame($target, $response->getHeaderLine('Location'));
                    }
                    $this->assertZeroCounts($probeState, 'registry ' . $method . ' ' . $requestPath);
                    self::assertSame([
                        HealthHandler::class => 0,
                        ReadyHandler::class  => 0,
                    ], $probeState->handlerCalls);
                    self::assertSame([], $this->files($sessionRoot));
                }
            }
        }

        self::assertSame($attributeCacheHash, hash_file('sha256', $cachedConfig['routing_attributes']['cache']['file']), 'Fresh containers must read, not rewrite, the compiled route cache.');
        self::assertSame($radixCacheHash, hash_file('sha256', $cachedConfig['router']['radix'][CacheConfig::File->value]), 'Fresh containers must read, not rewrite, the radix cache.');
    }

    /** @param array<string, mixed> $config */
    private function assertNormalRoutingBoundary(array $config): void
    {
        $cached = $config['router']['radix'][CacheConfig::Enabled->value];
        if ($cached) {
            @unlink($config['test_markers']['attributes']);
            @unlink($config['test_markers']['radix']);
        }
        $boundaryState                                                              = new BoundaryState();
        $dependencies                                                               = $config['dependencies'];
        $dependencies['services']['config']                                         = $config;
        $dependencies['services'][ProjectSettingsApiFacadeInterface::class]         = $this->createStub(ProjectSettingsApiFacadeInterface::class);
        $beforeRouting                                                              = [
            HtmlCacheControlMiddleware::class,
            ServerUrlMiddleware::class,
            RequestSizeLimitMiddleware::class,
            BodyParamsMiddleware::class,
            SessionMiddleware::class,
            FlashMessageMiddleware::class,
            CsrfValidationMiddleware::class,
            StripPackageMappingErrorInertiaDecorationMiddleware::class,
            InertiaMiddleware::class,
            ApiAuthenticationAttemptRateLimitMiddleware::class,
        ];
        foreach ($beforeRouting as $service) {
            unset($dependencies['factories'][$service], $dependencies['invokables'][$service], $dependencies['aliases'][$service]);
            $dependencies['factories'][$service] = static fn () => new BoundaryMiddleware($boundaryState, $service);
        }
        $dependencies['delegators'][RuntimeCorsMiddleware::class][] = static fn ($container, string $name, callable $callback): MiddlewareInterface => new BoundaryCorsMiddleware($boundaryState, $callback());
        $service                                                    = OptionalRouteAuthenticationMiddleware::class;
        unset($dependencies['factories'][$service], $dependencies['invokables'][$service], $dependencies['aliases'][$service]);
        $dependencies['factories'][$service]      = static fn () => new BoundaryMiddleware($boundaryState, 'after-routing', true);
        $serviceManager                           = new ServiceManager($dependencies);
        $application                              = $serviceManager->get(Application::class);
        $middlewareFactory                        = $serviceManager->get(MiddlewareFactory::class);
        (require $this->root . '/config/pipeline.php')($application, $middlewareFactory, $serviceManager);
        (require $this->root . '/config/routes.php')($application, $middlewareFactory, $serviceManager);
        $application->get('/__probe-boundary/:id', BoundaryRouteHandler::class, 'probe-boundary');
        $serviceManager->get(RouteCollector::class)->getRoutes();
        $router    = $serviceManager->get(RouterInterface::class);
        self::assertInstanceOf(RadixRouter::class, $router, 'The ordinary stack must use the raw shared RadixRouter.');
        $router->match($this->request('GET', '/health', 'packio.us'));
        if ($cached) {
            self::assertFileExists($config['test_markers']['attributes'], 'Normal boundary fixture did not load the compiled route attributes.');
            self::assertFileExists($config['test_markers']['radix'], 'Normal boundary fixture did not load the compiled Radix artifact.');
        }
        $adapter = $serviceManager->get(ImplicitHeadRouterAdapter::class);
        self::assertSame([
            'id' => '42',
        ], $adapter->match($this->request('GET', '/__probe-boundary/42', 'packio.us'))->getMatchedParams());
        self::assertTrue($adapter->match($this->request('HEAD', '/__probe-boundary/42', 'packio.us'))->isFailure(), 'The probe adapter must share the raw router while restoring native implicit HEAD handling locally.');

        foreach (['GET', 'HEAD'] as $method) {
            $boundaryState->reset();
            $original            = $this->request($method, '/__probe-boundary/42?source=test', 'packio.us');
            $response            = $application->handle($original);
            $cors                = $boundaryState->observed(RuntimeCorsMiddleware::class);
            $afterRouting        = $boundaryState->observed('after-routing');
            self::assertSame($original, $cors['request'], 'The selector must pass the original request object into the main stack.');
            self::assertSame($method, $cors['method'], 'CORS boundary must observe the original method before routing.');
            self::assertNull($cors['result']);
            self::assertNull($cors['params']);
            self::assertNull($cors['id']);
            self::assertNull($cors['forwardedMethod']);
            self::assertSame($method, $afterRouting['method'], 'Normal routing must not rewrite HEAD before late implicit HEAD handling.');
            self::assertNull($afterRouting['forwardedMethod']);
            self::assertInstanceOf(RouteResult::class, $afterRouting['result']);
            self::assertSame([
                'id' => '42',
            ], $afterRouting['params']);
            self::assertSame(200, $response->getStatusCode());
        }
    }

    /** @param array<string, mixed> $config */
    private function exerciseMatrix(string $mode, array $config, bool $cached): void
    {
        foreach (['GET', 'HEAD'] as $method) {
            foreach (['/health', '/ready'] as $path) {
                foreach ([[false, false], [false, true], [true, true]] as [$withCookie, $withOrigin]) {
                    if ($cached) {
                        @unlink($config['test_markers']['attributes']);
                        @unlink($config['test_markers']['radix']);
                    }
                    $container = $this->application($config);
                    if ($cached) {
                        self::assertFileExists($config['test_markers']['attributes'], 'Fresh container did not load the compiled route artifact.');
                    }
                    $probeState = $container['state'];
                    $this->assertZeroCounts($probeState, $mode . ' before ' . $method . ' ' . $path);
                    $request  = $this->request($method, $path, 'packio.us', $withCookie, withOrigin: $withOrigin);
                    $started  = microtime(true);
                    $response = $container['application']->handle($request);
                    $elapsed  = microtime(true) - $started;
                    $this->assertZeroCounts($probeState, $mode . ' after ' . $method . ' ' . $path);
                    if ($cached) {
                        self::assertFileExists($config['test_markers']['radix'], 'Fresh container did not load the Radix artifact.');
                    }

                    $expectedStatus = '/health' === $path ? 200 : 503;
                    self::assertSame($expectedStatus, $response->getStatusCode(), $mode . ' ' . $method . ' ' . $path . ' body=' . $response->getBody());
                    $expectedBody = '/health' === $path ? '{"status":"OK"}' : '{"status":"not_ready"}';
                    self::assertSame('HEAD' === $method ? '' : $expectedBody, (string) $response->getBody());
                    self::assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
                    if ('/ready' === $path) {
                        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
                        self::assertLessThan(2.0, $elapsed, 'Readiness request exceeded the 2 second budget.');
                    }
                    self::assertFalse($response->hasHeader('Set-Cookie'));
                    self::assertSame([], $this->files($config['session']['test_path']));
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function config(bool $cached, string $cacheRoot, string $sessionRoot, string $archiveRoot): array
    {
        $routingCacheRoot = $cacheRoot . ($cached ? '/compiled' : '/discovery');
        if (! is_dir($routingCacheRoot)) {
            mkdir($routingCacheRoot, 0o700, true);
        }
        $oldDirectory        = getcwd();
        $isolatedConfigCache = $this->fixtureDirectory . '/config-cache';
        if (! is_dir($isolatedConfigCache)) {
            mkdir($isolatedConfigCache, 0o700, true);
        }

        try {
            self::assertTrue(chdir($isolatedConfigCache));
            $config = require $this->root . '/config/config.php';
        } finally {
            chdir($oldDirectory ?: $this->root);
        }
        ini_set('session.save_path', $sessionRoot);
        ini_set('session.save_handler', 'files');

        $config['config_cache_path']           = $isolatedConfigCache . '/config-cache.php';
        $config['routing_attributes']['cache'] = [
            'enabled' => $cached,
            'file'    => $routingCacheRoot . '/attributes.php',
        ];
        $config['routing_attributes']['discovery']['enabled'] = true;
        $config['routing_attributes']['discovery']['paths']   = [$this->root . '/src'];
        foreach ($config['routing_attributes']['discovery']['psr4']['mappings'] as $namespace => $path) {
            $config['routing_attributes']['discovery']['psr4']['mappings'][$namespace] = $this->root . '/' . $path;
        }
        $config['router']['radix'][CacheConfig::Enabled->value]  = $cached;
        $config['router']['radix'][CacheConfig::File->value]     = $routingCacheRoot . '/radix.php';
        $config['cycle']['db-config']['connections']['postgres'] = new PostgresDriverConfig(
            connection: new TcpConnectionConfig(database: 'packio_probe', host: '127.0.0.1', port: 1, user: 'probe', password: 'probe'),
            reconnect: false,
            timezone: 'UTC',
            queryCache: false,
        );
        $config['cycle']['schema']['compiled']['path']                                = $this->fixtureDirectory . '/schema.php';
        $config['storage']['archive']['driver']                                       = 'local';
        $config['storage']['archive']['local']['path']                                = $archiveRoot;
        $config['twig']['cache_dir']                                                  = $routingCacheRoot . '/twig';
        $config['sirix_mezzio_valinor']['mapper']['cache_dir']                        = $routingCacheRoot . '/valinor';
        $config['cycle']['migrator']['directory']                                     = $routingCacheRoot . '/migrations';
        $config['object_mapper']['cache_dir']                                         = $routingCacheRoot . '/mapper';
        $config['security']['rate_limits']['storage_dir']                             = $routingCacheRoot . '/rate-limits';
        $config['router']['radix'][CacheConfig::File->value]                          = $routingCacheRoot . '/radix.php';
        $config['session']['test_path']                                               = $sessionRoot;
        file_put_contents($config['cycle']['schema']['compiled']['path'], "<?php\nreturn [];\n");
        foreach ([$routingCacheRoot . '/twig', $routingCacheRoot . '/mapper', $routingCacheRoot . '/proxies', $routingCacheRoot . '/rate-limits', $routingCacheRoot . '/valinor', $routingCacheRoot . '/migrations'] as $path) {
            if (! is_dir($path)) {
                mkdir($path, 0o700, true);
            }
        }
        $config['dependencies']['lazy_services']['proxies_target_dir'] = $routingCacheRoot . '/proxies';
        $config['test_markers']                                        = [
            'attributes' => $this->fixtureDirectory . '/attributes-cache-hit',
            'radix'      => $this->fixtureDirectory . '/radix-cache-hit',
        ];
        $config['dependencies']['services']['config'] = $config;

        return $config;
    }

    /** @param array<string, mixed> $config */
    private function seedRouteCaches(array $config): void
    {
        $serviceManager = $this->buildContainer($config);
        $serviceManager->get(Application::class)->get('/__probe-boundary/:id', BoundaryRouteHandler::class, 'probe-boundary');
        $serviceManager->get(RouteCollector::class)->getRoutes();
        $serviceManager->get(RouterInterface::class)->match($this->request('GET', '/health', 'packio.us'));
    }

    /** @param array<string, mixed> $config */
    private function assertReadinessFixtureReachesPostgres(array $config): void
    {
        $driver     = $config['cycle']['db-config']['connections']['postgres'];
        $cachePaths = [
            dirname($config['cycle']['schema']['compiled']['path']),
            $config['object_mapper']['cache_dir'],
            $config['dependencies']['lazy_services']['proxies_target_dir'],
            $config['twig']['cache_dir'],
            $config['sirix_mezzio_valinor']['mapper']['cache_dir'],
        ];

        try {
            ReadinessProbe::check([
                'dsn'              => $driver->connection->getDsn(),
                'user'             => $driver->connection->getUsername(),
                'password'         => $driver->connection->getPassword(),
                'schemas'          => $driver->schema,
                'schema_file'      => $config['cycle']['schema']['compiled']['path'],
                'migrations_dir'   => $config['cycle']['migrator']['directory'],
                'migrations_table' => $config['cycle']['migrator']['table'],
                'archive'          => $config['storage']['archive']['local']['path'],
                'cache'            => $cachePaths,
                'settings'         => [],
            ]);
            self::fail('The readiness fixture unexpectedly connected to PostgreSQL.');
        } catch (PDOException) {
            self::assertFileIsReadable($config['cycle']['schema']['compiled']['path']);
            self::assertDirectoryIsReadable($config['storage']['archive']['local']['path']);
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{application: Application, state: ProbeState}
     */
    private function application(array $config): array
    {
        $serviceManager         = $this->buildContainer($config);
        $application            = $serviceManager->get(Application::class);
        $middlewareFactory      = $serviceManager->get(MiddlewareFactory::class);
        (require $this->root . '/config/pipeline.php')($application, $middlewareFactory, $serviceManager);
        (require $this->root . '/config/routes.php')($application, $middlewareFactory, $serviceManager);
        $serviceManager->get(RouteCollector::class)->getRoutes();
        $serviceManager->get(RouterInterface::class);
        $probeState = $serviceManager->get('__probe_state');
        self::assertInstanceOf(ProbeState::class, $probeState);

        return [
            'application' => $application,
            'state'       => $probeState,
        ];
    }

    /** @param array<string, mixed> $config */
    private function buildContainer(array $config): ServiceManager
    {
        $config['dependencies']['services']['config'] = $config;
        self::assertArrayHasKey(OrganizationsHandler::class, $config['dependencies']['factories'], 'The unrelated DB-dependent handler must be a real configured production service.');
        $probeState = new ProbeState();
        $guarded    = [
            RuntimeCorsMiddleware::class,
            SessionMiddleware::class,
            FlashMessageMiddleware::class,
            CsrfValidationMiddleware::class,
            AuthenticationProfileProviderInterface::class,
            OptionalRouteAuthenticationMiddleware::class,
            ApiAuthenticationAttemptRateLimitMiddleware::class,
            ResolveCurrentUserMiddleware::class,
            ResolveCurrentOrganizationMiddleware::class,
            ResolveCurrentMembershipMiddleware::class,
            ResolveOrganizationActorContextMiddleware::class,
            HandleInertiaRequests::class,
            InertiaMiddleware::class,
            HtmlCacheControlMiddleware::class,
            ServerUrlMiddleware::class,
            RequestSizeLimitMiddleware::class,
            BodyParamsMiddleware::class,
            StripPackageMappingErrorInertiaDecorationMiddleware::class,
            RefreshAuthenticatedSessionMiddleware::class,
            RequireUnbannedUserMiddleware::class,
            RequireEmailVerificationMiddleware::class,
            HandleAuthorizationExceptionMiddleware::class,
            ResolveRegistryOrganizationAttributeMiddleware::class,
            ImplicitOptionsMiddleware::class,
            MethodNotAllowedMiddleware::class,
            UrlHelperMiddleware::class,
            'dbal',
            'orm',
            ProjectSettingsApiFacadeInterface::class,
            OrganizationsHandler::class,
        ];
        foreach ($guarded as $id) {
            unset($config['dependencies']['invokables'][$id], $config['dependencies']['aliases'][$id], $config['dependencies']['services'][$id]);
            $config['dependencies']['factories'][$id] = static function() use ($probeState, $id): never {
                $probeState->factories[$id] = ($probeState->factories[$id] ?? 0) + 1;

                throw new RuntimeException('Forbidden eager service construction: ' . $id);
            };
        }
        unset($config['dependencies']['factories'][OrganizationApiFacade::class], $config['dependencies']['invokables'][OrganizationApiFacade::class]);
        $config['dependencies']['factories'][OrganizationApiFacade::class] = static function() use ($probeState): never {
            $probeState->factories[OrganizationApiFacade::class] = ($probeState->factories[OrganizationApiFacade::class] ?? 0) + 1;

            throw new RuntimeException('Organization facade proxy initialized during probe setup.');
        };
        $config['dependencies']['services']['__probe_state']          = $probeState;
        $config['dependencies']['delegators'][HealthHandler::class][] = static function($container, string $name, callable $callback) use ($probeState): object {
            ++$probeState->handlerCalls[$name];

            return $callback();
        };
        $config['dependencies']['delegators'][ReadyHandler::class][] = static function($container, string $name, callable $callback) use ($probeState): object {
            ++$probeState->handlerCalls[$name];

            return $callback();
        };
        $serviceManager = new ServiceManager($config['dependencies']);
        $serviceManager->get(FileRateLimiter::class);
        $serviceManager->get(OrganizationApiFacadeInterface::class);

        return $serviceManager;
    }

    private function prependArtifactMarker(string $artifact, string $marker): void
    {
        $contents = file_get_contents($artifact);
        self::assertIsString($contents);
        $strictTypes    = strpos($contents, 'declare(strict_types=1);');
        $insertionPoint = false === $strictTypes ? 5 : $strictTypes + strlen('declare(strict_types=1);');
        file_put_contents($artifact, substr($contents, 0, $insertionPoint) . "\nfile_put_contents(" . var_export($marker, true) . ", 'loaded');\n" . substr($contents, $insertionPoint));
    }

    private function request(
        string $method,
        string $path,
        string $host,
        bool $withCookie = false,
        bool $inertia = false,
        ?bool $withOrigin = null,
    ): ServerRequestInterface {
        $headers = [
            'Host' => $host,
        ];
        if ($withCookie) {
            $headers['Cookie'] = 'PACKIO_SESSID=probe-session';
        }

        if ($withOrigin ?? $withCookie) {
            $headers['Origin'] = 'https://packio.us';
        }

        if ($inertia) {
            $headers['X-Inertia'] = 'true';
        }
        $uriPath        = parse_url($path, PHP_URL_PATH);
        $uriQuery       = parse_url($path, PHP_URL_QUERY);
        $serverRequest  = new ServerRequest([], [], 'https://' . $host . $path, $method, 'php://memory', $headers)
            ->withUri(new ServerRequest()->getUri()->withScheme('https')->withHost($host)->withPath(is_string($uriPath) ? $uriPath : '/')->withQuery(is_string($uriQuery) ? $uriQuery : ''))
        ;
        if ($withCookie) {
            return $serverRequest->withCookieParams([
                'PACKIO_SESSID' => 'probe-session',
            ]);
        }

        return $serverRequest;
    }

    private function assertZeroCounts(ProbeState $probeState, string $where): void
    {
        foreach ($probeState->factories as $id => $count) {
            self::assertSame(0, $count, $where . ' resolved guarded service ' . $id);
        }
        self::assertSame([], $probeState->factories, $where . ' must not invoke any guarded service factory.');
    }

    /** @return list<string> */
    private function files(string $directory): array
    {
        $files = [];
        foreach (scandir($directory) ?: [] as $entry) {
            if ('.' !== $entry && '..' !== $entry) {
                $files[] = $entry;
            }
        }

        return $files;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}

final class ProbeState
{
    /** @var array<string, int> */
    public array $factories = [];

    /** @var array<string, int> */
    public array $handlerCalls = [
        HealthHandler::class => 0,
        ReadyHandler::class  => 0,
    ];
}

final class BoundaryState
{
    /** @var array<string, array{request: ServerRequestInterface, method: string, result: mixed, params: mixed, id: mixed, forwardedMethod: mixed}> */
    private array $observations = [];

    public function reset(): void
    {
        $this->observations = [];
    }

    /** @return array{request: ServerRequestInterface, method: string, result: mixed, params: mixed, id: mixed, forwardedMethod: mixed} */
    public function observed(string $stage): array
    {
        TestCase::assertArrayHasKey($stage, $this->observations);

        return $this->observations[$stage];
    }

    public function record(string $stage, ServerRequestInterface $serverRequest): void
    {
        $result                     = $serverRequest->getAttribute(RouteResult::class);
        $this->observations[$stage] = [
            'request'         => $serverRequest,
            'method'          => $serverRequest->getMethod(),
            'result'          => $result,
            'params'          => $result instanceof RouteResult ? $result->getMatchedParams() : null,
            'id'              => $serverRequest->getAttribute('id'),
            'forwardedMethod' => $serverRequest->getAttribute('forwarded_http_method'),
        ];
    }
}

final readonly class BoundaryCorsMiddleware implements MiddlewareInterface
{
    public function __construct(private BoundaryState $boundaryState, private MiddlewareInterface $middleware) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->boundaryState->record(RuntimeCorsMiddleware::class, $request);

        return $this->middleware->process($request, $handler);
    }
}

final readonly class BoundaryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private BoundaryState $boundaryState,
        private string $stage,
        private bool $stop = false,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (RuntimeCorsMiddleware::class === $this->stage || 'after-routing' === $this->stage) {
            $this->boundaryState->record($this->stage, $request);
        }

        return $this->stop ? new Response() : $handler->handle($request);
    }
}

final readonly class BoundaryRouteHandler implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new Response();
    }
}
