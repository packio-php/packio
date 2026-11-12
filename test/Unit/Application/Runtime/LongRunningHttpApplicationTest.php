<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Runtime;

use Common\Runtime\RuntimeResetterInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Packio\Application\Runtime\ApplicationRequestFailedException;
use Packio\Application\Runtime\LongRunningHttpApplication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sirix\ObjectMapper\Contract\WarmableObjectMapperInterface;
use Throwable;

#[CoversClass(LongRunningHttpApplication::class)]
final class LongRunningHttpApplicationTest extends TestCase
{
    #[Test]
    public function itWarmsObjectMappersBeforeLoggingLongRunningRuntimeBootstrap(): void
    {
        $events          = [];
        $logger          = $this->createMock(LoggerInterface::class);
        $objectMapper    = $this->createMock(WarmableObjectMapperInterface::class);
        $runtimeResetter = $this->createStub(RuntimeResetterInterface::class);
        $application     = $this->createStub(RequestHandlerInterface::class);

        $objectMapper->expects(self::once())
            ->method('warmup')
            ->willReturnCallback(static function() use (&$events): array {
                $events[] = 'warmed';

                return [];
            })
        ;
        $logger->expects(self::once())
            ->method('info')
            ->willReturnCallback(static function() use (&$events): void {
                $events[] = 'logged';
            })
        ;

        new LongRunningHttpApplication($application, $runtimeResetter, $logger, $objectMapper)->bootstrap('frankenphp');

        self::assertSame(['warmed', 'logged'], $events);
    }

    #[Test]
    public function itSendsTheApplicationResponseBeforeResettingTheRuntime(): void
    {
        $events                     = [];
        $response                   = new Response();
        $application                = $this->createMock(RequestHandlerInterface::class);
        $runtimeResetter            = $this->createMock(RuntimeResetterInterface::class);
        $longRunningHttpApplication = new LongRunningHttpApplication($application, $runtimeResetter);

        $application->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function() use (&$events, $response): ResponseInterface {
                $events[] = 'handled';

                return $response;
            })
        ;
        $runtimeResetter->expects(self::once())
            ->method('reset')
            ->willReturnCallback(static function() use (&$events): void {
                $events[] = 'reset';
            })
        ;

        $longRunningHttpApplication->handleRequest(
            new ServerRequest(),
            static function(ResponseInterface $sentResponse) use (&$events, $response): void {
                self::assertSame($response, $sentResponse);
                $events[] = 'sent';
            },
        );

        self::assertSame(['handled', 'sent', 'reset'], $events);
    }

    #[Test]
    public function itSendsAFailureResponseBeforeResettingWhenTheApplicationThrows(): void
    {
        $events                     = [];
        $runtimeException           = new RuntimeException('Application failed');
        $application                = $this->createMock(RequestHandlerInterface::class);
        $runtimeResetter            = $this->createMock(RuntimeResetterInterface::class);
        $longRunningHttpApplication = new LongRunningHttpApplication($application, $runtimeResetter);

        $application->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function() use (&$events, $runtimeException): never {
                $events[] = 'handled';

                throw $runtimeException;
            })
        ;
        $runtimeResetter->expects(self::once())
            ->method('reset')
            ->willReturnCallback(static function() use (&$events): void {
                $events[] = 'reset';
            })
        ;

        $this->assertApplicationFailure($runtimeException, static function() use ($longRunningHttpApplication, &$events): void {
            $longRunningHttpApplication->handleRequest(
                new ServerRequest(),
                static function(ResponseInterface $response): void {},
                static function(Throwable $throwable) use (&$events): void {
                    self::assertInstanceOf(RuntimeException::class, $throwable);
                    $events[] = 'failure response sent';
                },
            );
        });

        self::assertSame(['handled', 'failure response sent', 'reset'], $events);
    }

    #[Test]
    public function itResetsExactlyOnceWhenTheResponseTransportThrows(): void
    {
        $events                     = [];
        $runtimeException           = new RuntimeException('Transport failed');
        $application                = $this->createMock(RequestHandlerInterface::class);
        $runtimeResetter            = $this->createMock(RuntimeResetterInterface::class);
        $longRunningHttpApplication = new LongRunningHttpApplication($application, $runtimeResetter);

        $application->expects(self::once())
            ->method('handle')
            ->willReturnCallback(static function() use (&$events): ResponseInterface {
                $events[] = 'handled';

                return new Response();
            })
        ;
        $runtimeResetter->expects(self::once())
            ->method('reset')
            ->willReturnCallback(static function() use (&$events): void {
                $events[] = 'reset';
            })
        ;

        $this->assertThrows($runtimeException, static function() use ($longRunningHttpApplication, &$events, $runtimeException): void {
            $longRunningHttpApplication->handleRequest(new ServerRequest(), static function(ResponseInterface $response) use (&$events, $runtimeException): never {
                $events[] = 'sent';

                throw $runtimeException;
            });
        });

        self::assertSame(['handled', 'sent', 'reset'], $events);
    }

    #[Test]
    public function itDoesNotHideAFailureResponseTransportExceptionAsAnApplicationFailure(): void
    {
        $events                     = [];
        $applicationFailure         = new RuntimeException('Application failed');
        $transportFailure           = new RuntimeException('Failure response transport failed');
        $application                = $this->createMock(RequestHandlerInterface::class);
        $runtimeResetter            = $this->createMock(RuntimeResetterInterface::class);
        $longRunningHttpApplication = new LongRunningHttpApplication($application, $runtimeResetter);

        $application->expects(self::once())->method('handle')->willThrowException($applicationFailure);
        $runtimeResetter->expects(self::once())->method('reset')->willReturnCallback(static function() use (&$events): void {
            $events[] = 'reset';
        });

        $this->assertThrows($transportFailure, static function() use ($longRunningHttpApplication, &$events, $transportFailure): void {
            $longRunningHttpApplication->handleRequest(
                new ServerRequest(),
                static function(ResponseInterface $response): void {},
                static function(Throwable $throwable) use (&$events, $transportFailure): never {
                    $events[] = 'failure response sent';

                    throw $transportFailure;
                },
            );
        });

        self::assertSame(['failure response sent', 'reset'], $events);
    }

    #[Test]
    public function itDoesNotLogAnApplicationExceptionMessage(): void
    {
        $application        = $this->createMock(RequestHandlerInterface::class);
        $resetter           = $this->createStub(RuntimeResetterInterface::class);
        $logger             = $this->createMock(LoggerInterface::class);
        $runtimeException   = new RuntimeException('database password is secret');

        $application->expects(self::once())->method('handle')->willThrowException($runtimeException);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Long-running HTTP request failed',
                self::callback(static fn (array $context): bool => ! isset($context['message'])
                    && RuntimeException::class === $context['exception_class']),
            )
        ;

        $longRunningHttpApplication = new LongRunningHttpApplication($application, $resetter, $logger);

        $this->assertApplicationFailure($runtimeException, static function() use ($longRunningHttpApplication): void {
            $longRunningHttpApplication->handleRequest(new ServerRequest(), static function(ResponseInterface $response): void {});
        });
    }

    private function assertThrows(Throwable $throwable, callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected an exception to be thrown.');
        } catch (Throwable $exception) {
            self::assertSame($throwable, $exception);
        }
    }

    private function assertApplicationFailure(Throwable $throwable, callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected an exception to be thrown.');
        } catch (ApplicationRequestFailedException $exception) {
            self::assertSame($throwable, $exception->getPrevious());
        }
    }
}
