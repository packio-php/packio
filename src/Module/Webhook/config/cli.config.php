<?php

declare(strict_types=1);

use Packio\Webhook\Infrastructure\Cli\WebhookEventsPruneCommand;

return [
    'laminas-cli' => [
        'commands' => [
            'webhook:events:prune' => WebhookEventsPruneCommand::class,
        ],
    ],
];
