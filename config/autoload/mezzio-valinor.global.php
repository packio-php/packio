<?php

declare(strict_types=1);

use CuyZ\Valinor\Mapper\Configurator\MapKeysToCamelCase;

return [
    'sirix_mezzio_valinor' => [
        'mapper' => [
            'cache_dir'                  => \env(
                'VALINOR_CACHE_DIR',
                \env('APP_PRODUCTION', false, 'bool') ? 'data/cache/valinor' : '',
                'string',
            ),
            'cache_watch'                => \env('VALINOR_CACHE_WATCH', false, 'bool'),
            'configurators'              => [
                MapKeysToCamelCase::class,
            ],
            'allow_superfluous_keys'     => true,
            'allow_scalar_value_casting' => true,
            'allow_permissive_types'     => false,
            'allow_undefined_values'     => false,
            'support_date_formats'       => [],
        ],
    ],
];
