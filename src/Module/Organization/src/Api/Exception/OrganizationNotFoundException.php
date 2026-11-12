<?php

declare(strict_types=1);

namespace Packio\Organization\Api\Exception;

use RuntimeException;

final class OrganizationNotFoundException extends RuntimeException implements OrganizationExceptionInterface
{
    public function __construct()
    {
        parent::__construct('not_found');
    }

    public function userMessage(): string
    {
        return 'Organization not found.';
    }
}
