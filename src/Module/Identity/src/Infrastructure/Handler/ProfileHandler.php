<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Query\IdentityQueryService;
use Packio\Identity\Infrastructure\Page\ProfileViewModelFactory;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Authenticated]
#[Get('/profile', name: 'profile')]
final readonly class ProfileHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityQueryService $identityQueryService,
        private IdentityApiFacadeInterface $identityApiFacade,
        private ProfileViewModelFactory $profileViewModelFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        $currentUser = RequestContextAttributes::currentUser($request);

        return $inertia->render('Profile/Show', $this->profileViewModelFactory->create(
            $currentUser,
            $currentUser instanceof UserDto ? $this->identityQueryService->findOAuthAccountsByUserId($currentUser->id) : [],
            $currentUser instanceof UserDto ? $this->identityApiFacade->apiAccessTokensForUser($currentUser->id) : [],
        ));
    }
}
