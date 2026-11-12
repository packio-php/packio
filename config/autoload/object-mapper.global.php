<?php

declare(strict_types=1);

$isProduction = \env('APP_PRODUCTION', false, 'bool');

return [
    'object_mapper' => [
        // ObjectMapperFactory isolates generated mappers in the cache-format
        // subdirectory after validating this configured base path.
        'cache_dir'                => \env('OBJECT_MAPPER_CACHE_DIR', 'data/cache/object-mapper', 'string'),
        'generate_on_demand'       => \env('OBJECT_MAPPER_GENERATE_ON_DEMAND', ! $isProduction, 'bool'),
        'reuse_prepared_mappings'  => \env('OBJECT_MAPPER_REUSE_PREPARED_MAPPINGS', $isProduction, 'bool'),
    ],
];
