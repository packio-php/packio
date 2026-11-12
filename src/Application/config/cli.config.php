<?php

declare(strict_types=1);

use Packio\Application\Infrastructure\Cli\RateLimitCleanupCommand;

return [
    'laminas-cli' => [
        'commands' => [
            'rate-limit:cleanup' => RateLimitCleanupCommand::class,
        ],
    ],
];
