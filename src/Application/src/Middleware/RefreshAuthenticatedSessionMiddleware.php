<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Mezzio\Session\SessionInterface;
use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\Contract\AuthContextInterface;

use function time;

final readonly class RefreshAuthenticatedSessionMiddleware implements MiddlewareInterface
{
    private const string LAST_ACTIVITY_KEY = '_packio_session_last_activity';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (ApiPathMatcher::isApiRequest($request)) {
            return $handler->handle($request);
        }

        $context = $request->getAttribute(AuthenticationAttributes::Context->value);
        $session = $request->getAttribute(SessionInterface::class) ?? $request->getAttribute('session');

        if ($context instanceof AuthContextInterface && $context->check() && $session instanceof SessionInterface) {
            $session->set(self::LAST_ACTIVITY_KEY, time());
        }

        return $handler->handle($request);
    }
}
