<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Config;

use Common\Branding\BrandingConfig;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Cors\Middleware\CorsMiddleware;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouterInterface;
use Mezzio\Session\SessionMiddleware;
use Packio\Admin\Api\ProjectSettingsApiFacadeInterface;
use Packio\Admin\Api\RuntimeSettingKeysApiFacadeInterface;
use Packio\Application\Infrastructure\Handler\HealthHandler;
use Packio\Application\Infrastructure\Handler\ReadyHandler;
use Packio\Application\Infrastructure\Readiness\ReadinessChecker;
use Packio\Application\Middleware\OperationalProbeDispatchMiddleware;
use Packio\Application\Middleware\OptionalRouteAuthenticationMiddleware;
use Packio\Application\Middleware\StripPackageMappingErrorInertiaDecorationMiddleware;
use Packio\Application\Router\ImplicitHeadRouterAdapter;
use Packio\Registry\Api\ArchiveStorageApiFacadeInterface;
use Packio\Registry\Application\Service\RegistryOrganizationResolver;
use Packio\Registry\Infrastructure\Middleware\RestrictRegistrySubdomainMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\Mezzio\Router\RadixRouter;

use function array_key_exists;
use function dirname;

final class DependenciesConfigTest extends TestCase
{
    #[Test]
    public function selectorDependenciesAndRawRouterAliasAreConfigured(): void
    {
        $config                          = require dirname(__DIR__, 4) . '/src/Application/config/dependencies.config.php';
        $config[ConfigAbstractFactory::class] += (require dirname(__DIR__, 4) . '/config/autoload/dependencies.global.php')[ConfigAbstractFactory::class];
        $applicationOverridesRouterAlias = array_key_exists(RouterInterface::class, $config['dependencies']['aliases']);

        $config['dependencies']['invokables'][DispatchMiddleware::class]                = DispatchMiddleware::class;
        $branding                                                                       = new BrandingConfig();
        $config['dependencies']['services'][RadixRouter::class]                         = new RadixRouter();
        $config['dependencies']['services'][StreamFactoryInterface::class]              = new StreamFactory();
        $config['dependencies']['services'][RestrictRegistrySubdomainMiddleware::class] = new RestrictRegistrySubdomainMiddleware(
            new RegistryOrganizationResolver($branding),
            $branding,
        );
        $config['dependencies']['aliases'][RouterInterface::class] = RadixRouter::class;
        $forbiddenFactoryCalls                                     = 0;
        foreach ([
            CorsMiddleware::class,
            SessionMiddleware::class,
            ProjectSettingsApiFacadeInterface::class,
            OptionalRouteAuthenticationMiddleware::class,
            HealthHandler::class,
            ReadyHandler::class,
            'dbal',
            'orm',
        ] as $forbiddenService) {
            unset($config['dependencies']['invokables'][$forbiddenService]);
            $config['dependencies']['factories'][$forbiddenService] = static function() use (&$forbiddenFactoryCalls): never {
                ++$forbiddenFactoryCalls;

                throw new RuntimeException('The probe selector must not resolve main-stack services.');
            };
        }
        $config['dependencies']['services']['config']                    = $config;
        $container                                                       = new ServiceManager($config['dependencies']);

        self::assertInstanceOf(
            StripPackageMappingErrorInertiaDecorationMiddleware::class,
            $container->get(StripPackageMappingErrorInertiaDecorationMiddleware::class),
        );
        $selector = $container->get(OperationalProbeDispatchMiddleware::class);
        $adapter  = $container->get(ImplicitHeadRouterAdapter::class);
        self::assertInstanceOf(OperationalProbeDispatchMiddleware::class, $selector);
        self::assertInstanceOf(ImplicitHeadRouterAdapter::class, $adapter);
        self::assertFalse($applicationOverridesRouterAlias, 'The application must leave RouterInterface to the vendor RadixRouter alias.');
        $router    = $container->get(RouterInterface::class);
        $collector = new RouteCollector($router);
        $collector->get('/shared', new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        }, 'shared');
        self::assertSame('shared', $adapter->match(new ServerRequest(uri: 'https://packio.us/shared'))->getMatchedRouteName());
        self::assertSame(0, $forbiddenFactoryCalls);
    }

    #[Test]
    public function readinessServicesAreResolvableWithoutDatabaseAccess(): void
    {
        $config                                                                          = require dirname(__DIR__, 4) . '/src/Application/config/dependencies.config.php';
        $config['dependencies']['services']['config']                                    = $config;
        $config['dependencies']['services'][RuntimeSettingKeysApiFacadeInterface::class] = self::createStub(RuntimeSettingKeysApiFacadeInterface::class);
        $config['dependencies']['services'][ArchiveStorageApiFacadeInterface::class]     = self::createStub(ArchiveStorageApiFacadeInterface::class);
        $databaseFactoryCalls                                                            = 0;
        $config['dependencies']['factories']['dbal']                                     = static function() use (&$databaseFactoryCalls): never {
            ++$databaseFactoryCalls;

            throw new RuntimeException('Database service must not be resolved.');
        };
        $config['dependencies']['factories']['orm'] = static function() use (&$databaseFactoryCalls): never {
            ++$databaseFactoryCalls;

            throw new RuntimeException('ORM service must not be resolved.');
        };
        $container = new ServiceManager($config['dependencies']);

        self::assertInstanceOf(ReadinessChecker::class, $container->get(ReadinessChecker::class));
        self::assertInstanceOf(ReadyHandler::class, $container->get(ReadyHandler::class));
        self::assertSame(0, $databaseFactoryCalls);
    }
}
