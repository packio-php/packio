<?php

declare(strict_types=1);

return [
    'vite' => [
        'options' => [
            'is_dev_mode'      => false,
            'vite_build_dir'   => 'public/build',
            'vite_public_base' => '/build',
            'dev_server'       => \env('VITE_DEV_SERVER_URL', 'http://localhost:5173'),
        ],
    ],
];
