<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command\OAuth;

use Packio\Identity\Application\Result\OAuthCallbackResult;

interface OAuthContinuationInterface
{
    public function submit(ResolveOAuthCallbackCommand $resolveOAuthCallbackCommand): OAuthCallbackResult;

    public function verify(
        string $userId,
        string $code,
        ?ResolveOAuthCallbackCommand $resolveOAuthCallbackCommand,
        bool $oauthRequired
    ): OAuthCallbackResult;
}
