<?php

declare(strict_types=1);

use Packio\Organization\Infrastructure\Cli\OrganizationInvitationsEncryptCommand;

return [
    'laminas-cli' => [
        'commands' => [
            'organization:invitations:encrypt' => OrganizationInvitationsEncryptCommand::class,
        ],
    ],
];
