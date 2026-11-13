<?php

declare(strict_types=1);

use Packio\Audit\Infrastructure\Cli\AuditLogPruneCommand;

return [
    'laminas-cli' => [
        'commands' => [
            'audit:log:prune' => AuditLogPruneCommand::class,
        ],
    ],
];
