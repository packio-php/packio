<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Exception;

use RuntimeException;

/**
 * Thrown when token refresh is not possible (no refresh token or provider does not support it).
 * Indicates that reauthorization is required.
 */
final class SourceTokenRefreshUnavailableException extends RuntimeException {}
