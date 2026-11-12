<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Request;

use Yiisoft\Validator\Rule\Required;

final readonly class SourceDeleteRequest
{
    public function __construct(#[Required] public string $sourceId = '') {}
}
