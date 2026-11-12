<?php

declare(strict_types=1);

namespace Packio\Application\Service;

use Psr\Http\Message\ServerRequestInterface;

use function in_array;

final readonly class InitialListViewModeResolver
{
    public function resolve(ServerRequestInterface $serverRequest, string $storageKey, string $default = 'grid'): string
    {
        $cookies = $serverRequest->getCookieParams();
        $mode    = $cookies[$storageKey] ?? null;
        $default = in_array($default, ['grid', 'table'], true) ? $default : 'grid';

        return in_array($mode, ['grid', 'table'], true) ? $mode : $default;
    }
}
