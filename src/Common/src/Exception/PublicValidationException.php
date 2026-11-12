<?php

declare(strict_types=1);

namespace Common\Exception;

use InvalidArgumentException;

/** Only fixed user-facing validation messages may be supplied to this type. */
final class PublicValidationException extends InvalidArgumentException {}
