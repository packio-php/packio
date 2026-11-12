<?php

declare(strict_types=1);

namespace Packio\Source\Api\Exception;

use InvalidArgumentException;

final class InvalidSourceSettings extends InvalidArgumentException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid source settings.');
    }
}
