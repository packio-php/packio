<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Error;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Stratigility\Middleware\ErrorHandler;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Packio\Application\Error\HttpErrorLoggingDelegator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sirix\Mezzio\Authentication\Exception\AlreadyAuthenticatedException;
use Sirix\Mezzio\Authentication\Exception\AuthenticationException;
use Throwable;

use function json_encode;

final class HttpErrorLoggingDelegatorTest extends TestCase
{
    public function testAuthenticationRedirectIsNotLogged(): void
    {
        self::assertSame([], $this->handle(new AuthenticationException())->getRecords());
    }

    public function testAlreadyAuthenticatedRedirectIsNotLogged(): void
    {
        self::assertSame([], $this->handle(new AlreadyAuthenticatedException())->getRecords());
    }

    public function testUnexpectedExceptionIsLoggedOnceWithRedactedContext(): void
    {
        $records = $this->handle(new RuntimeException('Authorization: Bearer private-secret'))->getRecords();

        self::assertCount(1, $records);
        $record = $records[0];
        self::assertSame(Level::Error, $record->level);
        self::assertSame('Uncaught HTTP exception', $record->message);
        self::assertSame(500, $record->context['status']);
        self::assertSame('GET', $record->context['method']);
        self::assertSame('/boom', $record->context['path']);
        self::assertArrayHasKey('correlation_id', $record->context);
        self::assertSame(RuntimeException::class, $record->context['exception']['class']);
        self::assertStringNotContainsString('private-secret', json_encode($record->context, JSON_THROW_ON_ERROR));
        foreach (['message', 'file', 'line', 'exception_class'] as $key) {
            self::assertArrayNotHasKey($key, $record->context);
        }
    }

    private function handle(Throwable $throwable): TestHandler
    {
        $testHandler = new TestHandler();
        $logger      = new Logger('test', [$testHandler]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnMap([[LoggerInterface::class, $logger]]);

        $errorHandler = new HttpErrorLoggingDelegator()($container, ErrorHandler::class, static fn (): ErrorHandler => new ErrorHandler(new ResponseFactory()));
        $errorHandler->process(
            new ServerRequest([], [], '/boom', 'GET'),
            new readonly class($throwable) implements RequestHandlerInterface {
                public function __construct(private Throwable $throwable) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw $this->throwable;
                }
            },
        );

        return $testHandler;
    }
}
