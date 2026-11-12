<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Result;

use Packio\Identity\Application\Command\OAuth\ResolveOAuthCallbackCommand;

final readonly class OAuthCallbackResult
{
    public function __construct(
        public OAuthCallbackStatus $status,
        public bool $userCreated = false,
        public ?string $userId = null,
        public ?string $email = null,
        public ?string $displayName = null,
        public ?ResolveOAuthCallbackCommand $pendingOAuth = null,
    ) {}
}
