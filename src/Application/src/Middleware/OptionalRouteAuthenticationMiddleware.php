<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Packio\Application\Routing\AuthenticationRouteOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Contract\AuthenticationProfileProviderInterface;

final readonly class OptionalRouteAuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthenticationProfileProviderInterface $authenticationProfileProvider) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authenticationProfile = $this->authenticationProfileProvider->getDefaultProfile();
        $routeProfile          = AuthenticationRouteOptions::profile($request);

        if (null !== $routeProfile) {
            $selectedProfile = $this->authenticationProfileProvider->get($routeProfile);

            if ($selectedProfile->name() !== $authenticationProfile->name()) {
                return $handler->handle($request);
            }
        }

        return $authenticationProfile->optionalAuthenticateMiddleware()->process($request, $handler);
    }
}
