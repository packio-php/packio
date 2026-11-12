<?php

declare(strict_types=1);

use Packio\Release\Infrastructure\Cli\ReleaseOrphansCleanupCommand;

return [
    'laminas-cli' => [
        'commands' => [
            'release:orphans:cleanup' => ReleaseOrphansCleanupCommand::class,
        ],
    ],
];
