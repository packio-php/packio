<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Support;

use Packio\Identity\Infrastructure\Dto\OAuthCallbackInput;

use function hash_equals;

final class OAuthCallbackStateValidator
{
    public function isValid(string $storedState, OAuthCallbackInput $oAuthCallbackInput): bool
    {
        if ('' === $oAuthCallbackInput->receivedState || '' === $storedState) {
            return false;
        }

        $stateToCompare = $oAuthCallbackInput->decodedState->state ?? $oAuthCallbackInput->receivedState;

        return hash_equals($storedState, $stateToCompare);
    }
}
