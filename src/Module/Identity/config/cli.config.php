<?php

declare(strict_types=1);

use Packio\Identity\Infrastructure\Cli\EmailVerificationCleanupCommand;
use Packio\Identity\Infrastructure\Cli\MakeUserAdminCommand;
use Packio\Identity\Infrastructure\Cli\PasswordResetCleanupCommand;
use Packio\Identity\Infrastructure\Cli\SignInHistoryPruneCommand;

return [
    'laminas-cli' => [
        'commands' => [
            'email-verification:cleanup' => EmailVerificationCleanupCommand::class,
            'user:make-admin'            => MakeUserAdminCommand::class,
            'password-reset:cleanup'     => PasswordResetCleanupCommand::class,
            'sign-in-history:prune'      => SignInHistoryPruneCommand::class,
        ],
    ],
];
