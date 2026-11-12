<?php

declare(strict_types=1);

namespace Packio\Rest\Infrastructure\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: '3.1.0',
    info: new OA\Info(
        version: '1.0.0',
        description: 'Stateless JSON API for Packio automation.',
        title: 'Packio API',
    ),
    servers: [
        new OA\Server(url: '/', description: 'Current Packio instance'),
    ],
    security: [
        [
            'BearerAuth' => [],
        ],
        [
            'ApiToken' => [],
        ],
    ],
)]
#[OA\SecurityScheme(securityScheme: 'BearerAuth', type: 'http', bearerFormat: 'Packio API token', scheme: 'bearer')]
#[OA\SecurityScheme(
    securityScheme: 'ApiToken',
    type: 'apiKey',
    name: 'X-API-TOKEN',
    in: 'header',
)]
final class OpenApiDocument {}
