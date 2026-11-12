<?php

declare(strict_types=1);

return [
    'inertia' => [
        'ssr'           => [
            'enabled'         => \env('INERTIA_SSR_ENABLED', true, 'bool'),
            'url'             => \env('INERTIA_SSR_URL', 'http://127.0.0.1:13714/render'),
            'timeout'         => \env('INERTIA_SSR_TIMEOUT', 1.0, 'float'),
            'connect_timeout' => \env('INERTIA_SSR_CONNECT_TIMEOUT', 1.0, 'float'),
        ],
        'exclude_paths' => [
            '/health',
            '/webhooks/*',
            '/packages.json',
            '/private/packages.json',
            '/p2/*',
            '/dist/*',
            '/api/*',
            '/sitemap.xml',
        ],
    ],
];
