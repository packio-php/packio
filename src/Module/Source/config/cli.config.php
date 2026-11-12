<?php

declare(strict_types=1);

use Packio\Source\Infrastructure\Cli\SourceOAuthRefreshMaintenanceCommand;
use Packio\Source\Infrastructure\Cli\SourceWebhookSecretsEncryptCommand;

return [
    'laminas-cli' => [
        'commands' => [
            'source:oauth:refresh-maintenance' => SourceOAuthRefreshMaintenanceCommand::class,
            'source:webhook-secrets:encrypt'   => SourceWebhookSecretsEncryptCommand::class,
        ],
    ],
];
