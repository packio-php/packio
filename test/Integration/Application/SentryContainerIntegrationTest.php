<?php

declare(strict_types=1);

namespace PackioTest\Integration\Application;

use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Laminas\ServiceManager\ServiceManager;
use League\Event\EventDispatcher as LeagueEventDispatcher;
use Packio\Source\Infrastructure\Cli\SourceOAuthRefreshMaintenanceCommand;
use Packio\Worker\Infrastructure\Cli\WorkerRunCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Sentry\State\HubInterface;
use Sirix\Mezzio\Authentication\Exception\AlreadyAuthenticatedException;
use Sirix\Mezzio\Authentication\Exception\AuthenticationException;
use Sirix\Redaction\RedactorInterface;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;
use Sirix\SentryPsr\Reporter\SentryReporter;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;

use function array_key_exists;
use function dirname;
use function file_exists;
use function putenv;
use function sprintf;
use function unlink;

final class SentryContainerIntegrationTest extends TestCase
{
    #[Test]
    public function sentryServicesResolveWithEmptyDsn(): void
    {
        $serviceManager = $this->containerWithEmptySentryDsn();

        self::assertTrue($serviceManager->has(HubInterface::class));
        self::assertTrue($serviceManager->has(SentryLifecycle::class));
        self::assertTrue($serviceManager->has(SentryReporter::class));
        self::assertTrue($serviceManager->has(SentryErrorMiddleware::class));
        self::assertTrue($serviceManager->has(RedactorInterface::class));

        self::assertInstanceOf(HubInterface::class, $serviceManager->get(HubInterface::class));
        self::assertInstanceOf(SentryLifecycle::class, $serviceManager->get(SentryLifecycle::class));
        self::assertInstanceOf(SentryReporter::class, $serviceManager->get(SentryReporter::class));
        self::assertInstanceOf(SentryErrorMiddleware::class, $serviceManager->get(SentryErrorMiddleware::class));
        self::assertInstanceOf(RedactorInterface::class, $serviceManager->get(RedactorInterface::class));
    }

    #[Test]
    public function sentryConfigUsesSafeStringFallbacksAndTypedEnvValues(): void
    {
        $serviceManager = $this->containerWithEmptySentryDsn();
        $config         = $serviceManager->get('config');

        self::assertIsArray($config);
        self::assertTrue(array_key_exists('dsn', $config['sentry']));
        self::assertNull($config['sentry']['dsn']);
        self::assertSame('local', $config['sentry']['environment'] ?? null);
        self::assertSame(1.0, $config['sentry']['sample_rate'] ?? null);
        self::assertSame(0.0, $config['sentry']['traces_sample_rate'] ?? null);
        self::assertFalse($config['sentry']['send_default_pii'] ?? true);
        self::assertFalse($config['sentry_psr']['flush_on_http_error'] ?? true);
        self::assertSame(2, $config['sentry_psr']['flush_timeout'] ?? null);
        self::assertFalse($config['sentry_psr']['http_context']['capture_headers'] ?? true);
        self::assertFalse($config['sentry_psr']['http_context']['capture_query_string'] ?? true);
        self::assertFalse($config['sentry_psr']['capture_console_input'] ?? true);
        self::assertSame([
            AuthenticationException::class,
            AlreadyAuthenticatedException::class,
        ], $config['sentry_psr']['exception_filter']['ignore_classes'] ?? null);
    }

    #[Test]
    public function sentryConsoleDispatcherDoesNotReplaceDomainEventDispatcher(): void
    {
        $serviceManager = $this->containerWithEmptySentryDsn();
        $config         = $serviceManager->get('config');

        self::assertIsArray($config);
        self::assertSame(
            LeagueEventDispatcher::class,
            $config['dependencies']['aliases'][EventDispatcherInterface::class] ?? null,
        );
        self::assertInstanceOf(
            SymfonyEventDispatcher::class,
            $serviceManager->get('Laminas\Cli\SymfonyEventDispatcher'),
        );
    }

    #[Test]
    public function sentryCommandWiringUsesDependencyConfig(): void
    {
        $serviceManager = $this->containerWithEmptySentryDsn();
        $config         = $serviceManager->get('config');

        self::assertIsArray($config);
        self::assertSame(
            ConfigAbstractFactory::class,
            $config['dependencies']['factories'][WorkerRunCommand::class] ?? null,
        );
        self::assertSame(
            ConfigAbstractFactory::class,
            $config['dependencies']['factories'][SourceOAuthRefreshMaintenanceCommand::class] ?? null,
        );

        $configAbstractFactory = $config[ConfigAbstractFactory::class] ?? null;
        self::assertIsArray($configAbstractFactory);

        $workerCommandDependencies = $configAbstractFactory[WorkerRunCommand::class] ?? null;
        self::assertIsArray($workerCommandDependencies);
        self::assertContains(SentryLifecycle::class, $workerCommandDependencies);
        self::assertContains(SentryReporter::class, $workerCommandDependencies);

        $sourceCommandDependencies = $configAbstractFactory[SourceOAuthRefreshMaintenanceCommand::class] ?? null;
        self::assertIsArray($sourceCommandDependencies);
        self::assertContains(SentryLifecycle::class, $sourceCommandDependencies);
        self::assertContains(SentryReporter::class, $sourceCommandDependencies);
    }

    private function containerWithEmptySentryDsn(): ServiceManager
    {
        foreach ([
            'APP_PRODUCTION=false',
            'SENTRY_DSN=',
            'SENTRY_ENVIRONMENT=',
            'SENTRY_RELEASE=',
            'SENTRY_SAMPLE_RATE=1.0',
            'SENTRY_TRACES_SAMPLE_RATE=0.0',
            'SENTRY_FLUSH_ON_HTTP_ERROR=false',
            'SENTRY_FLUSH_TIMEOUT=2',
            'SENTRY_CAPTURE_CONSOLE_INPUT=false',
        ] as $env) {
            putenv($env);
        }

        $rootPath        = dirname(__DIR__, 3);
        $configCachePath = $rootPath . '/data/cache/config-cache.php';
        if (file_exists($configCachePath)) {
            unlink($configCachePath);
        }

        /** @var array<string, mixed> $config */
        $config = require $rootPath . '/config/config.php';

        $dependencies = $config['dependencies'] ?? null;
        self::assertIsArray($dependencies, sprintf('Config does not contain dependencies in %s.', $rootPath));

        $dependencies['services']['config'] = $config;

        return new ServiceManager($dependencies);
    }
}
