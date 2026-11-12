<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command\OAuth;

use Packio\Identity\Domain\ValueObject\OAuthProvider;
use Packio\Identity\Domain\ValueObject\OAuthTokenResponse;
use Packio\Identity\Domain\ValueObject\OAuthUserData;

final readonly class ResolveOAuthCallbackCommand
{
    /** @param list<string> $scopes */
    public function __construct(
        public OAuthProvider $provider,
        public OAuthTokenResponse $tokens,
        public OAuthUserData $userData,
        public array $scopes = [],
        public ?string $currentUserId = null,
        public string $registrationRedirectTarget = '',
        public ?string $email = null,
        public bool $verifyEmailFromProvider = true,
    ) {}
}
