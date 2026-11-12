<?php

declare(strict_types=1);

namespace Packio\Application\Runtime;

use Composer\InstalledVersions;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function getmypid;
use function hrtime;
use function intdiv;

final readonly class RoadRunnerHttpEntrypoint
{
    public function __construct(
        private LongRunningHttpApplication $longRunningHttpApplication,
        private LoggerInterface $logger,
    ) {}

    public function run(RoadRunnerHttpTransportInterface $roadRunnerHttpTransport): void
    {
        $this->logStart();

        while (true) {
            try {
                $request = $roadRunnerHttpTransport->waitRequest();
            } catch (MalformedRoadRunnerRequestException $exception) {
                $previous = $exception->getPrevious();

                $this->logger->warning('RoadRunner HTTP request mapping failed', [
                    'exception_class' => $previous instanceof Throwable ? $previous::class : $exception::class,
                ]);

                if (! $this->respondToMalformedRequest($roadRunnerHttpTransport)) {
                    return;
                }

                continue;
            } catch (Throwable $exception) {
                $this->reportFailureAndStop($roadRunnerHttpTransport, $exception);

                return;
            }

            if (! $request instanceof ServerRequestInterface) {
                $this->logger->info('RoadRunner HTTP worker stopped gracefully', [
                    'runtime' => 'roadrunner',
                    'pid'     => getmypid(),
                ]);

                return;
            }

            $requestStartedAt = hrtime(true);
            $responseStatus   = null;

            try {
                $this->longRunningHttpApplication->handleRequest(
                    $request,
                    static function(ResponseInterface $response) use ($roadRunnerHttpTransport, &$responseStatus): void {
                        $roadRunnerHttpTransport->respond($response);
                        $responseStatus = $response->getStatusCode();
                    },
                    static function() use ($roadRunnerHttpTransport, &$responseStatus): void {
                        $response = self::internalServerErrorResponse();
                        $roadRunnerHttpTransport->respond($response);
                        $responseStatus = $response->getStatusCode();
                    },
                );
            } catch (ApplicationRequestFailedException $exception) {
                if (! $this->reportApplicationFailure($roadRunnerHttpTransport, $exception)) {
                    $this->stopWorker($roadRunnerHttpTransport);

                    return;
                }
            } catch (Throwable $exception) {
                $this->reportFailureAndStop($roadRunnerHttpTransport, $exception);

                return;
            } finally {
                if (null !== $responseStatus) {
                    $this->logRequestCompleted($request, $responseStatus, $requestStartedAt);
                }
            }
        }
    }

    private function logStart(): void
    {
        $this->longRunningHttpApplication->bootstrap('roadrunner');

        $this->logger->info('RoadRunner HTTP worker started', [
            'runtime'            => 'roadrunner',
            'pid'                => getmypid(),
            'roadrunner_version' => InstalledVersions::getPrettyVersion('spiral/roadrunner') ?? 'unknown',
        ]);
    }

    private function respondToMalformedRequest(RoadRunnerHttpTransportInterface $roadRunnerHttpTransport): bool
    {
        try {
            $roadRunnerHttpTransport->respond(new TextResponse('Bad Request', 400));
        } catch (Throwable $exception) {
            $this->reportFailureAndStop($roadRunnerHttpTransport, $exception);

            return false;
        }

        return true;
    }

    private function reportApplicationFailure(RoadRunnerHttpTransportInterface $roadRunnerHttpTransport, Throwable $throwable): bool
    {
        return $this->reportFailure($roadRunnerHttpTransport, $throwable->getPrevious() ?? $throwable);
    }

    private function reportFailureAndStop(RoadRunnerHttpTransportInterface $roadRunnerHttpTransport, Throwable $throwable): void
    {
        $this->reportFailure($roadRunnerHttpTransport, $throwable);

        $this->stopWorker($roadRunnerHttpTransport);
    }

    private function reportFailure(RoadRunnerHttpTransportInterface $roadRunnerHttpTransport, Throwable $throwable): bool
    {
        $message = 'RoadRunner HTTP request failed: ' . $throwable::class;

        $this->logger->error('RoadRunner HTTP request failed', [
            'exception_class' => $throwable::class,
        ]);

        try {
            $roadRunnerHttpTransport->reportError($message);
        } catch (Throwable $errorReportingException) {
            $this->logger->error('RoadRunner HTTP worker error reporting failed', [
                'exception_class' => $errorReportingException::class,
            ]);

            return false;
        }

        return true;
    }

    private function stopWorker(RoadRunnerHttpTransportInterface $roadRunnerHttpTransport): void
    {
        try {
            $roadRunnerHttpTransport->stop();
        } catch (Throwable $stopException) {
            $this->logger->error('RoadRunner HTTP worker stop failed', [
                'exception_class' => $stopException::class,
            ]);
        }
    }

    private function logRequestCompleted(ServerRequestInterface $serverRequest, int $responseStatus, int $requestStartedAt): void
    {
        $this->logger->info('RoadRunner HTTP request completed', [
            'method'      => $serverRequest->getMethod(),
            'path'        => $serverRequest->getUri()->getPath(),
            'status'      => $responseStatus,
            'duration_ms' => intdiv(hrtime(true) - $requestStartedAt, 1_000_000),
        ]);
    }

    private static function internalServerErrorResponse(): ResponseInterface
    {
        return new TextResponse('Internal Server Error', 500);
    }
}
