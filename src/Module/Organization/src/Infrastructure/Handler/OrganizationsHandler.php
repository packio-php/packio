<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

#[Authenticated]
#[Get('/organizations', name: 'organizations.index')]
final readonly class OrganizationsHandler implements RequestHandlerInterface
{
    public function __construct(private OrganizationApiFacadeInterface $organizationApiFacade) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
        $userId  = RequestContextAttributes::currentUserId($request);

        if (null === $userId) {
            return $inertia->render('Organizations/Index', [
                'organizations'     => [],
                'has_organizations' => false,
            ]);
        }

        $organizations = [];
        foreach ($this->organizationApiFacade->activeOrganizationsForUser($userId) as $organizationDto) {
            $organizations[] = $organizationDto->toArray();
        }

        return $inertia->render('Organizations/Index', [
            'organizations'     => $organizations,
            'has_organizations' => [] !== $organizations,
        ]);
    }
}
