<?php

declare(strict_types=1);

return [
    'session' => [
        'persistence'     => [
            'ext' => [
                'non_locking'                    => false,
                'delete_cookie_on_empty_session' => false,
            ],
        ],
        'name'            => 'PACKIO_SESSID',
        'cookie_lifetime' => 7200,
        'cookie_path'     => '/',
        'cookie_domain'   => null,
        'cookie_secure'   => \env('APP_PRODUCTION', false, 'bool'),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cache_limiter'   => 'nocache',
        'cache_expire'    => 180,
    ],
];
