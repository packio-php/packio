<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Error;

use Laminas\Diactoros\ServerRequest;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sentry\State\HubInterface;
use Sirix\SentryPsr\Lifecycle\SentryLifecycle;
use Sirix\SentryPsr\Middleware\SentryErrorMiddleware;
use Sirix\SentryPsr\Middleware\SentryErrorMiddlewareFactory;

use function array_replace_recursive;

final class SentryErrorMiddlewareConfigTest extends TestCase
{
    public function testMergedConfigWiresTheVendorFactory(): void
    {
        $config = require __DIR__ . '/../../../../config/config.php';

        self::assertSame(SentryErrorMiddlewareFactory::class, $config['dependencies']['factories'][SentryErrorMiddleware::class]);
    }

    public function testCapturedExceptionIsRethrownWithoutAnUnredactedLogRecord(): void
    {
        $testHandler   = new TestHandler();
        $logger        = new Logger('test', [$testHandler]);
        $hub           = $this->createStub(HubInterface::class);

        $services = [
            'config'                => [
                'sentry_psr' => array_replace_recursive(
                    (require __DIR__ . '/../../../../config/autoload/sentry.global.php')['sentry_psr'],
                    [
                        'isolate_http_scope'  => false,
                        'flush_on_http_error' => false,
                    ],
                ),
            ],
            HubInterface::class     => $hub,
            SentryLifecycle::class  => new SentryLifecycle($hub),
            LoggerInterface::class  => $logger,
        ];
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => isset($services[$id]));
        $container->method('get')->willReturnCallback(static fn (string $id): mixed => $services[$id]);

        $middleware = new SentryErrorMiddlewareFactory()($container);

        $this->expectException(RuntimeException::class);

        try {
            $middleware->process(new ServerRequest([], [], '/boom', 'GET'), new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new RuntimeException('Authorization: Bearer private-secret');
                }
            });
        } finally {
            self::assertSame([], $testHandler->getRecords());
        }
    }
}
