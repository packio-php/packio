<?php

declare(strict_types=1);

namespace Packio\Organization\Api\Exception;

use Throwable;

interface OrganizationExceptionInterface extends Throwable
{
    public function userMessage(): string;
}
