<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Exception;

use RuntimeException;

/**
 * Thrown when the provider rejects a refresh token, so reauthorization is required.
 */
final class ProviderRefreshRejectedException extends RuntimeException {}
