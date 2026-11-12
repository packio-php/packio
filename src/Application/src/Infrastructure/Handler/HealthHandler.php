<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Get('/health', name: 'health')]
final readonly class HealthHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'status' => 'OK',
        ], headers: [
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
