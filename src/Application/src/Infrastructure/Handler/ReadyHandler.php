<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Readiness\ReadinessChecker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/ready', name: 'ready')]
final readonly class ReadyHandler implements RequestHandlerInterface
{
    public function __construct(private ReadinessChecker $readinessChecker) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $ready = $this->readinessChecker->isReady();

        return new JsonResponse([
            'status' => $ready ? 'ready' : 'not_ready',
        ], $ready ? 200 : 503, [
            'Cache-Control' => 'no-store',
            'X-Robots-Tag'  => 'noindex, nofollow',
        ]);
    }
}
