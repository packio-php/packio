<?php

declare(strict_types=1);

use Mezzio\Cors\Configuration\ConfigurationInterface;

$appUrl             = \rtrim((string) \env('APP_URL', 'http://packio.localhost:8080'), '/');
$allowedOrigins     = \env('CORS_ALLOWED_ORIGINS', [$appUrl], 'array');
$credentialsAllowed = \env('CORS_CREDENTIALS_ALLOWED', false, 'bool');

if ($credentialsAllowed && \in_array(ConfigurationInterface::ANY_ORIGIN, $allowedOrigins, true)) {
    throw new InvalidArgumentException('CORS_CREDENTIALS_ALLOWED cannot be true when CORS_ALLOWED_ORIGINS contains "*".');
}

return [
    'packio'                                         => [
        'cors' => [
            'enabled' => \env('CORS_ENABLED', true, 'bool'),
        ],
    ],

    ConfigurationInterface::CONFIGURATION_IDENTIFIER => [
        'allowed_origins'     => $allowedOrigins,
        'allowed_headers'     => \env('CORS_ALLOWED_HEADERS', [
            'Accept',
            'Authorization',
            'Content-Type',
            'X-API-TOKEN',
            'X-Inertia',
            'X-Inertia-Version',
            'X-Requested-With',
        ], 'array'),
        'allowed_max_age'     => (string) \env('CORS_ALLOWED_MAX_AGE', 600, 'int'),
        'credentials_allowed' => $credentialsAllowed,
        'exposed_headers'     => \env('CORS_EXPOSED_HEADERS', [
            'Content-Disposition',
            'Location',
            'Retry-After',
            'WWW-Authenticate',
            'X-Inertia',
            'X-Inertia-Location',
            'X-Inertia-Version',
        ], 'array'),
    ],
];
