<?php

declare(strict_types=1);

namespace Packio\Application\Routing\Attribute;

enum ValidationNotify: string
{
    case Flash = 'flash';
    case Toast = 'toast';
    case None  = 'none';
}
