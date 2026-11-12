<?php

declare(strict_types=1);

use Packio\Identity\Application\Event\EmailVerificationRequested;
use Packio\Identity\Application\Event\PasswordResetRequested;
use Packio\Identity\Application\Listener\SendEmailVerificationCodeListener;
use Packio\Identity\Application\Listener\SendPasswordResetLinkListener;
use Packio\Package\Api\Event\PackageDownloaded;
use Packio\Package\Application\Listener\IncrementDownloadCountListener;
use Webware\Event\ConfigKey;
use Webware\Event\ListenerPriority;

return [
    ConfigKey::Listeners->value => [
        // --- Identity ---
        [
            'event'    => EmailVerificationRequested::class,
            'listener' => SendEmailVerificationCodeListener::class,
            'priority' => ListenerPriority::Normal->value,
        ],
        [
            'event'    => PasswordResetRequested::class,
            'listener' => SendPasswordResetLinkListener::class,
            'priority' => ListenerPriority::Normal->value,
        ],
        // --- Package ---
        [
            'event'    => PackageDownloaded::class,
            'listener' => IncrementDownloadCountListener::class,
            'priority' => ListenerPriority::Normal->value,
        ],
    ],
];
