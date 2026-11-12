<?php

declare(strict_types=1);

namespace Packio\Identity\Application\Command\OAuth;

use Packio\Identity\Application\Result\OAuthCallbackResult;

interface OAuthCallbackTransactionInterface
{
    /** @param callable(): OAuthCallbackResult $callback */
    public function run(callable $callback): OAuthCallbackResult;
}
