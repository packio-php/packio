<?php

declare(strict_types=1);

use Packio\Identity\Infrastructure\Cli\EmailVerificationCleanupCommand;
use Packio\Identity\Infrastructure\Cli\MakeUserAdminCommand;
use Packio\Identity\Infrastructure\Cli\PasswordResetCleanupCommand;

return [
    'laminas-cli' => [
        'commands' => [
            'email-verification:cleanup' => EmailVerificationCleanupCommand::class,
            'user:make-admin'            => MakeUserAdminCommand::class,
            'password-reset:cleanup'     => PasswordResetCleanupCommand::class,
        ],
    ],
];
