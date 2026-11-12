<?php

declare(strict_types=1);

namespace Packio\Source\Domain\Exception;

use RuntimeException;

/**
 * Thrown when a source OAuth account exists but has no usable access token.
 */
final class SourceTokenMissingException extends RuntimeException {}
