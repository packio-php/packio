<?php

declare(strict_types=1);

namespace Packio\Application\Runtime;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RoadRunnerHttpTransportInterface
{
    public function waitRequest(): ?ServerRequestInterface;

    public function respond(ResponseInterface $response): void;

    public function reportError(string $message): void;

    /** Stop this worker so RoadRunner can replace it after a protocol failure. */
    public function stop(): void;
}
