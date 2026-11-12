<?php

declare(strict_types=1);

namespace Packio\Source\Api\Dto;

enum SourceSettingsStatus: string
{
    case Updated   = 'updated';
    case NotFound  = 'not_found';
    case Forbidden = 'forbidden';
}
