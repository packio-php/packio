<?php

declare(strict_types=1);

use Packio\Worker\Infrastructure\Cli\WorkerCleanupCommand;
use Packio\Worker\Infrastructure\Cli\WorkerRunCommand;

return [
    'laminas-cli' => [
        'commands' => [
            'worker:run'     => WorkerRunCommand::class,
            'worker:cleanup' => WorkerCleanupCommand::class,
        ],
    ],
];
