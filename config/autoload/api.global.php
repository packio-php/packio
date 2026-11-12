<?php

declare(strict_types=1);

$publicSwaggerUiAssets = \realpath(__DIR__ . '/../../public/api-doc/assets');
$nodeSwaggerUiAssets   = \realpath(__DIR__ . '/../../node_modules/swagger-ui-dist');

return [
    'api' => [
        'docs'       => [
            'enabled'           => \env('API_DOCS_ENABLED', true, 'bool'),
            'public'            => \env('API_DOCS_PUBLIC', true, 'bool'),
            'swagger_ui_assets' => $publicSwaggerUiAssets ?: ($nodeSwaggerUiAssets ?: 'node_modules/swagger-ui-dist'),
        ],
        'tokens'     => [
            'prefix' => \env('API_TOKEN_PREFIX', 'packio_api_'),
        ],
    ],
];
