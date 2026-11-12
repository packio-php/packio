<?php

declare(strict_types=1);

use Sirix\CsFixerConfig\ConfigBuilder;

return ConfigBuilder::create()
    ->inDir(__DIR__ . '/src')
    ->inDir(__DIR__ . '/test')
    ->inDir(__DIR__ . '/config')
    ->inDir(__DIR__ . '/bin')
    ->inDir(__DIR__ . '/data/db/cycle/migrations')
    ->setRules([
        '@PHP8x5Migration' => true,
        'PedroTroller/line_break_between_method_arguments' => [
            'max-args' => 4,
            'max-length' => 140,
            'automatic-argument-merge' => false,
            'inline-attributes' => false,
        ],
        'php_unit_test_class_requires_covers' => false,
        'php_unit_internal_class' => false,
        'phpdoc_to_comment' => false,
    ])
    ->getConfig()
    ->setCacheFile('data/cache/.php-cs-fixer.cache')
;
