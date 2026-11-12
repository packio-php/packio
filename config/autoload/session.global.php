<?php

declare(strict_types=1);

// PHP session settings (global)
\ini_set('session.save_path', __DIR__ . '/../../data/sessions');
\ini_set('session.save_handler', 'files');
\ini_set('session.gc_maxlifetime', 7200);
\ini_set('session.gc_probability', 1);
\ini_set('session.gc_divisor', 100);

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
