<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\Response;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;

final readonly class ApiResponseFactory
{
    /**
     * @param array<non-empty-string, array<string>|string> $headers
     */
    public function json(mixed $payload, int $status = 200, array $headers = []): ResponseInterface
    {
        return new JsonResponse($payload, $status, $headers);
    }

    /**
     * @param array<non-empty-string, array<string>|string> $headers
     */
    public function empty(int $status = 204, array $headers = []): ResponseInterface
    {
        return new JsonResponse(null, $status, $headers);
    }
}
