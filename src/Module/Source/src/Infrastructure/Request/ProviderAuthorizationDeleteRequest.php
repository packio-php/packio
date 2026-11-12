<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Request;

use Yiisoft\Validator\Rule\Regex;

final readonly class ProviderAuthorizationDeleteRequest
{
    public function __construct(
        #[Regex('#^$|^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$#i')]
        public string $newAuthorizationId = '',
    ) {}
}
