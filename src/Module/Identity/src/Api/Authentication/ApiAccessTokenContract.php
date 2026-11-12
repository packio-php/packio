<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Authentication;

/**
 * Public boundary contract for the API access token authentication profile.
 *
 * Both the token implementation (Identity module) and the API middleware that
 * validates authenticated contexts use these names; they must never drift
 * apart. Keeping them here avoids importing Identity infrastructure from the
 * API module.
 */
final class ApiAccessTokenContract
{
    public const string STORAGE = 'api_access_token';

    public const string PAYLOAD_ACCESS_TOKEN = self::STORAGE;

    private function __construct() {}
}
