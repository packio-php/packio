<?php

declare(strict_types=1);

namespace PackioTest\Unit\Application\Runtime;

use Common\Runtime\RuntimeResetterInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use LogicException;
use Packio\Application\Runtime\LongRunningHttpApplication;
use Packio\Application\Runtime\MalformedRoadRunnerRequestException;
use Packio\Application\Runtime\RoadRunnerHttpEntrypoint;
use Packio\Application\Runtime\RoadRunnerHttpTransportInterface;
use Packio\Application\Runtime\RoadRunnerProtocolException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use RuntimeException;
use Stringable;

use function array_shift;
use function is_int;
use function is_string;

#[CoversClass(RoadRunnerHttpEntrypoint::class)]
final class RoadRunnerHttpEntrypointTest extends TestCase
{
    #[Test]
    public function itHandlesARequestAndResetsBeforeWaitingForTheNextOne(): void
    {
        $roadRunnerHttpTransportEvents      = new RoadRunnerHttpTransportEvents();
        $application                        = $this->createMock(RequestHandlerInterface::class);
        $resetter                           = $this->createMock(RuntimeResetterInterface::class);
        $longRunningHttpApplication         = new LongRunningHttpApplication($application, $resetter);
        $fakeRoadRunnerHttpTransport        = new FakeRoadRunnerHttpTransport([new ServerRequest(), null], $roadRunnerHttpTransportEvents);

        $application->expects(self::once())->method('handle')->willReturn(new TextResponse('ok'));
        $resetter->expects(self::once())->method('reset')->willReturnCallback(static function() use ($roadRunnerHttpTransportEvents): void {
            $roadRunnerHttpTransportEvents->record('reset');
        });

        new RoadRunnerHttpEntrypoint($longRunningHttpApplication, new NullLogger())->run($fakeRoadRunnerHttpTransport);

        self::assertSame(['respond:200:ok', 'reset'], $roadRunnerHttpTransportEvents->all());
    }

    #[Test]
    public function itLogsAQueryFreeAccessRecordAfterSendingTheResponse(): void
    {
        $roadRunnerEntrypointTestLogger      = new RoadRunnerEntrypointTestLogger();
        $application                         = $this->createStub(RequestHandlerInterface::class);
        $serverRequest                       = new ServerRequest()->withUri(new Uri('https://packio.test/packages?token=secret'));
        $longRunningHttpApplication          = new LongRunningHttpApplication($application, $this->createStub(RuntimeResetterInterface::class));
        $fakeRoadRunnerHttpTransport         = new FakeRoadRunnerHttpTransport([$serverRequest, null], new RoadRunnerHttpTransportEvents());

        $application->method('handle')->willReturn(new TextResponse('ok'));

        new RoadRunnerHttpEntrypoint($longRunningHttpApplication, $roadRunnerEntrypointTestLogger)->run($fakeRoadRunnerHttpTransport);

        $accessRecord = $roadRunnerEntrypointTestLogger->accessRecord();

        self::assertSame('GET', $accessRecord['method']);
        self::assertSame('/packages', $accessRecord['path']);
        self::assertSame(200, $accessRecord['status']);
    }

    #[Test]
    public function itReturnsBadRequestAndContinuesAfterMalformedInput(): void
    {
        $roadRunnerHttpTransportEvents  = new RoadRunnerHttpTransportEvents();
        $longRunningHttpApplication     = new LongRunningHttpApplication(
            $this->createStub(RequestHandlerInterface::class),
            $this->createStub(RuntimeResetterInterface::class),
        );
        $fakeRoadRunnerHttpTransport = new FakeRoadRunnerHttpTransport([new MalformedRoadRunnerRequestException(new RuntimeException('invalid payload')), null], $roadRunnerHttpTransportEvents);

        new RoadRunnerHttpEntrypoint($longRunningHttpApplication, new NullLogger())->run($fakeRoadRunnerHttpTransport);

        self::assertSame(['respond:400:Bad Request'], $roadRunnerHttpTransportEvents->all());
    }

    #[Test]
    public function itReturnsInternalServerErrorReportsTheFailureAndResetsWhenApplicationFails(): void
    {
        $roadRunnerHttpTransportEvents      = new RoadRunnerHttpTransportEvents();
        $application                        = $this->createMock(RequestHandlerInterface::class);
        $resetter                           = $this->createMock(RuntimeResetterInterface::class);
        $longRunningHttpApplication         = new LongRunningHttpApplication($application, $resetter);
        $fakeRoadRunnerHttpTransport        = new FakeRoadRunnerHttpTransport([new ServerRequest(), null], $roadRunnerHttpTransportEvents);

        $application->expects(self::once())->method('handle')->willThrowException(new RuntimeException('failure'));
        $resetter->expects(self::once())->method('reset')->willReturnCallback(static function() use ($roadRunnerHttpTransportEvents): void {
            $roadRunnerHttpTransportEvents->record('reset');
        });

        new RoadRunnerHttpEntrypoint($longRunningHttpApplication, new NullLogger())->run($fakeRoadRunnerHttpTransport);

        self::assertSame(['respond:500:Internal Server Error', 'reset', 'error:RoadRunner HTTP request failed: RuntimeException'], $roadRunnerHttpTransportEvents->all());
    }

    #[Test]
    public function itReportsAndStopsAfterAResponseTransportFailureWithoutSendingASecondResponse(): void
    {
        $roadRunnerHttpTransportEvents      = new RoadRunnerHttpTransportEvents();
        $application                        = $this->createMock(RequestHandlerInterface::class);
        $resetter                           = $this->createMock(RuntimeResetterInterface::class);
        $longRunningHttpApplication         = new LongRunningHttpApplication($application, $resetter);
        $fakeRoadRunnerHttpTransport        = new FakeRoadRunnerHttpTransport([new ServerRequest(), null], $roadRunnerHttpTransportEvents, true);

        $application->expects(self::once())->method('handle')->willReturn(new Response());
        $resetter->expects(self::once())->method('reset')->willReturnCallback(static function() use ($roadRunnerHttpTransportEvents): void {
            $roadRunnerHttpTransportEvents->record('reset');
        });

        new RoadRunnerHttpEntrypoint($longRunningHttpApplication, new NullLogger())->run($fakeRoadRunnerHttpTransport);

        self::assertSame([
            'respond:200:',
            'reset',
            'error:RoadRunner HTTP request failed: RuntimeException',
            'stop',
        ], $roadRunnerHttpTransportEvents->all());
    }

    #[Test]
    public function itReportsAndStopsAfterAProtocolFailureWithoutAttemptingABadRequestResponse(): void
    {
        $roadRunnerHttpTransportEvents    = new RoadRunnerHttpTransportEvents();
        $longRunningHttpApplication       = new LongRunningHttpApplication($this->createStub(RequestHandlerInterface::class), $this->createStub(RuntimeResetterInterface::class));
        $fakeRoadRunnerHttpTransport      = new FakeRoadRunnerHttpTransport([new RoadRunnerProtocolException(new RuntimeException('relay failed'))], $roadRunnerHttpTransportEvents);

        new RoadRunnerHttpEntrypoint($longRunningHttpApplication, new NullLogger())->run($fakeRoadRunnerHttpTransport);

        self::assertSame([
            'error:RoadRunner HTTP request failed: Packio\Application\Runtime\RoadRunnerProtocolException',
            'stop',
        ], $roadRunnerHttpTransportEvents->all());
    }

    #[Test]
    public function itReportsAndStopsWhenTheBadRequestResponseCannotBeSent(): void
    {
        $roadRunnerHttpTransportEvents    = new RoadRunnerHttpTransportEvents();
        $longRunningHttpApplication       = new LongRunningHttpApplication($this->createStub(RequestHandlerInterface::class), $this->createStub(RuntimeResetterInterface::class));
        $fakeRoadRunnerHttpTransport      = new FakeRoadRunnerHttpTransport(
            [new MalformedRoadRunnerRequestException(new RuntimeException('invalid payload'))],
            $roadRunnerHttpTransportEvents,
            true,
        );

        new RoadRunnerHttpEntrypoint($longRunningHttpApplication, new NullLogger())->run($fakeRoadRunnerHttpTransport);

        self::assertSame([
            'respond:400:Bad Request',
            'error:RoadRunner HTTP request failed: RuntimeException',
            'stop',
        ], $roadRunnerHttpTransportEvents->all());
    }

    #[Test]
    public function itStopsWhenReportingAnApplicationFailureFails(): void
    {
        $roadRunnerHttpTransportEvents      = new RoadRunnerHttpTransportEvents();
        $application                        = $this->createStub(RequestHandlerInterface::class);
        $application->method('handle')->willThrowException(new RuntimeException('application failed'));
        $longRunningHttpApplication   = new LongRunningHttpApplication($application, $this->createStub(RuntimeResetterInterface::class));
        $fakeRoadRunnerHttpTransport  = new FakeRoadRunnerHttpTransport([new ServerRequest()], $roadRunnerHttpTransportEvents, false, true);

        new RoadRunnerHttpEntrypoint($longRunningHttpApplication, new NullLogger())->run($fakeRoadRunnerHttpTransport);

        self::assertSame([
            'respond:500:Internal Server Error',
            'error:RoadRunner HTTP request failed: RuntimeException',
            'stop',
        ], $roadRunnerHttpTransportEvents->all());
    }
}

final class FakeRoadRunnerHttpTransport implements RoadRunnerHttpTransportInterface
{
    /** @param list<null|RuntimeException|ServerRequestInterface> $requests */
    public function __construct(
        private array $requests,
        private readonly RoadRunnerHttpTransportEvents $roadRunnerHttpTransportEvents,
        private readonly bool $failFirstResponse = false,
        private readonly bool $failErrorReporting = false,
    ) {}

    public function waitRequest(): ?ServerRequestInterface
    {
        $request = array_shift($this->requests);

        if ($request instanceof RuntimeException) {
            throw $request;
        }

        return $request;
    }

    public function respond(ResponseInterface $response): void
    {
        $this->roadRunnerHttpTransportEvents->record('respond:' . $response->getStatusCode() . ':' . $response->getBody());

        if ($this->failFirstResponse) {
            throw new RuntimeException('response transport failed');
        }
    }

    public function reportError(string $message): void
    {
        $this->roadRunnerHttpTransportEvents->record('error:' . $message);

        if ($this->failErrorReporting) {
            throw new RuntimeException('error reporting failed');
        }
    }

    public function stop(): void
    {
        $this->roadRunnerHttpTransportEvents->record('stop');
    }
}

final class RoadRunnerHttpTransportEvents
{
    /** @var list<string> */
    private array $events = [];

    public function record(string $event): void
    {
        $this->events[] = $event;
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->events;
    }
}

final class RoadRunnerEntrypointTestLogger extends AbstractLogger
{
    /** @var null|array{method: string, path: string, status: int, duration_ms: int} */
    private ?array $accessRecord = null;

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        if (
            'RoadRunner HTTP request completed' === $message
            && is_string($context['method'] ?? null)
            && is_string($context['path'] ?? null)
            && is_int($context['status'] ?? null)
            && is_int($context['duration_ms'] ?? null)
        ) {
            $this->accessRecord = [
                'method'      => $context['method'],
                'path'        => $context['path'],
                'status'      => $context['status'],
                'duration_ms' => $context['duration_ms'],
            ];
        }
    }

    /** @return array{method: string, path: string, status: int, duration_ms: int} */
    public function accessRecord(): array
    {
        if (null === $this->accessRecord) {
            throw new LogicException('Expected RoadRunner access log record was not written.');
        }

        return $this->accessRecord;
    }
}
