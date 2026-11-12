<?php

declare(strict_types=1);

use Packio\Registry\Infrastructure\Cli\ArchiveBackfillCommand;
use Packio\Registry\Infrastructure\Cli\ArchiveDevCleanupCommand;

return [
    'laminas-cli' => [
        'commands' => [
            'storage:archives:backfill'    => ArchiveBackfillCommand::class,
            'storage:archives:cleanup-dev' => ArchiveDevCleanupCommand::class,
        ],
    ],
];
