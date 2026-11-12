<?php

declare(strict_types=1);

namespace Packio\Admin\Infrastructure\Http;

use Psr\Http\Message\ServerRequestInterface;

use function is_string;
use function str_starts_with;

final class AdminReturnUrl
{
    public static function from(ServerRequestInterface $serverRequest, string $listPath): string
    {
        $returnTo = $serverRequest->getQueryParams()['return_to'] ?? null;

        return is_string($returnTo) && ($listPath === $returnTo || str_starts_with($returnTo, $listPath . '?'))
            ? $returnTo
            : $listPath;
    }
}
