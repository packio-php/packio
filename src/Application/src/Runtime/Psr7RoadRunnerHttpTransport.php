<?php

declare(strict_types=1);

namespace Packio\Application\Runtime;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Goridge\Exception\GoridgeException;
use Spiral\RoadRunner\Exception\RoadRunnerException;
use Spiral\RoadRunner\Http\PSR7Worker;
use Throwable;

final readonly class Psr7RoadRunnerHttpTransport implements RoadRunnerHttpTransportInterface
{
    public function __construct(private PSR7Worker $psr7Worker) {}

    public function waitRequest(): ?ServerRequestInterface
    {
        try {
            return $this->psr7Worker->waitRequest(populateServer: false);
        } catch (Throwable $exception) {
            if ($exception instanceof RoadRunnerException || $exception instanceof GoridgeException) {
                throw new RoadRunnerProtocolException($exception);
            }

            throw new MalformedRoadRunnerRequestException($exception);
        }
    }

    public function respond(ResponseInterface $response): void
    {
        $this->psr7Worker->respond($response);
    }

    public function reportError(string $message): void
    {
        $this->psr7Worker->getWorker()->error($message);
    }

    public function stop(): void
    {
        $this->psr7Worker->getWorker()->stop();
    }
}
