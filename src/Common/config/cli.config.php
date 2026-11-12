<?php

declare(strict_types=1);

use Common\Infrastructure\Cli\ObjectMapperWarmupCommand;

return [
    'laminas-cli' => [
        'commands' => [
            'object-mapper:warmup' => ObjectMapperWarmupCommand::class,
        ],
    ],
];
