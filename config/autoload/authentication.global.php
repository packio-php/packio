<?php

declare(strict_types=1);
use Packio\Identity\Infrastructure\Authentication\ApiAccessTokenStorage;
use Packio\Rest\Infrastructure\Authentication\ApiAccessTokenTransport;

return [
    'authentication' => [
        'default_profile' => 'web',
        'transports'      => [
            'api_access_token' => ApiAccessTokenTransport::class,
        ],
        'storages'        => [
            'api_access_token' => ApiAccessTokenStorage::class,
        ],
        'profiles'        => [
            'web' => [
                'transport'         => 'cookie',
                'storage'           => 'session',
                'transport_options' => [
                    'name'      => 'packio_authentication',
                    'path'      => '/',
                    'domain'    => '',
                    'secure'    => \env('APP_PRODUCTION', false, 'bool'),
                    'http_only' => true,
                    'same_site' => 'Lax',
                ],
            ],
            'api' => [
                'transport' => 'api_access_token',
                'storage'   => 'api_access_token',
            ],
        ],
        'session'         => [
            'prefix' => '_authentication.tokens.',
        ],
        'actor'           => [
            'roles_key' => 'roles',
            'role_key'  => 'role',
        ],
    ],
];
