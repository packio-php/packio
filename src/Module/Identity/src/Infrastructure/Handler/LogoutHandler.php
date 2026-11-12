<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Authenticated]
#[Post('/auth/logout', name: 'auth.session.logout')]
final readonly class LogoutHandler implements RequestHandlerInterface
{
    public function __construct(
        private AuthManagerInterface $authManager,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->authManager->logout($request, new RedirectResponse('/auth/login'));
    }
}
