<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command\OAuth;

use Packio\Identity\Application\Result\OAuthCallbackResult;

interface BrowserOAuthWorkflowInterface
{
    public function handle(string $provider, string $code, string $redirectTo = '', ?string $codeVerifier = null): OAuthCallbackResult;
}
