<?php

declare(strict_types=1);

namespace Packio\Identity\Api\Exception;

use RuntimeException;

/**
 * Thrown when the OAuth provider rejects a refresh token (for example invalid_grant).
 */
final class OAuthRefreshRejectedException extends RuntimeException {}
