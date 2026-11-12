<?php

declare(strict_types=1);

namespace Packio\Application\Infrastructure\Http;

use Psr\Http\Message\ServerRequestInterface;

use function str_contains;
use function strtolower;

/**
 * Single authority for deciding whether a request expects a machine-readable
 * (JSON) response instead of an Inertia/web presentation.
 *
 * The API path takes priority over any header so API consumers always receive
 * structured errors. Otherwise a present X-Inertia header keeps the request in
 * web mode, and only an explicit JSON Accept header opts into JSON.
 */
final class RequestPresentationPolicy
{
    public function wantsJson(ServerRequestInterface $serverRequest): bool
    {
        if (ApiPathMatcher::isApiRequest($serverRequest)) {
            return true;
        }

        if ($serverRequest->hasHeader('X-Inertia')) {
            return false;
        }

        $accept = strtolower($serverRequest->getHeaderLine('Accept'));

        return str_contains($accept, 'application/json') || str_contains($accept, 'text/json');
    }

    public function isApiRequest(ServerRequestInterface $serverRequest): bool
    {
        return ApiPathMatcher::isApiRequest($serverRequest);
    }
}
