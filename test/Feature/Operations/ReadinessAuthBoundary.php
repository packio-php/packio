<?php

declare(strict_types=1);

namespace PackioTest\Feature\Operations;

final class ReadinessAuthBoundary
{
    /** @var list<array{method: string, routeName: null|false|string, hasRouteResult: bool}> */
    public array $observations = [];
}
