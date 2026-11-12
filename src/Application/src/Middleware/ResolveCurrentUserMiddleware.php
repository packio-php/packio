<?php

declare(strict_types=1);

namespace Packio\Application\Middleware;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Infrastructure\Http\ApiPathMatcher;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Shared\Api\Authentication\CurrentUserActorInterface;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\AuthenticationAttributes;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;

final readonly class ResolveCurrentUserMiddleware implements MiddlewareInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private AuthManagerInterface $authManager,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (ApiPathMatcher::isApiRequest($request)) {
            return $handler->handle($request);
        }

        $currentUser = $this->currentUserFromActor($request) ?? $this->identityApiFacade->currentUser($request);
        if (! $currentUser instanceof UserDto && $this->authManager->check($request)) {
            return $this->authManager->logout($request, new RedirectResponse('/auth/login'));
        }

        $request = RequestContextAttributes::withCurrentUser($request, $currentUser);

        return $handler->handle($request);
    }

    private function currentUserFromActor(ServerRequestInterface $serverRequest): ?UserDto
    {
        $actor = $serverRequest->getAttribute(AuthenticationAttributes::Actor->value);

        return $actor instanceof CurrentUserActorInterface ? $actor->currentUser() : null;
    }
}
