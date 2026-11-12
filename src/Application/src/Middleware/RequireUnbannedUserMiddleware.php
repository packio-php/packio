<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RequireUnbannedUserMiddleware implements MiddlewareInterface
{
    private const string BANNED_ACCOUNT_PATH = '/account/banned';
    private const string LOGOUT_PATH         = '/auth/logout';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (ApiPathMatcher::isApiRequest($request)) {
            return $handler->handle($request);
        }

        $user = RequestContextAttributes::currentUser($request);
        if (! $user instanceof UserDto || ! $user->isBanned || $this->isExempt($request)) {
            return $handler->handle($request);
        }

        return new RedirectResponse(self::BANNED_ACCOUNT_PATH);
    }

    private function isExempt(ServerRequestInterface $serverRequest): bool
    {
        $path   = $serverRequest->getUri()->getPath();
        $method = $serverRequest->getMethod();

        return ('GET' === $method && self::BANNED_ACCOUNT_PATH === $path)
            || ('POST' === $method && self::LOGOUT_PATH === $path);
    }
}
