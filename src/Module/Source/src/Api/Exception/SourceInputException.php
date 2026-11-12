<?php

declare(strict_types=1);

namespace Packio\Source\Api\Exception;

use RuntimeException;

/** Only fixed user-facing validation messages may be supplied to this type. */
final class SourceInputException extends RuntimeException {}
