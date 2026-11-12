<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Shared\Api\Dto\OrganizationDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post(
    '/organization/:organization/sources/authorizations/:authorizationId/check',
    name: 'sources.authorizations.check',
)]
final readonly class SourceAuthorizationCheckHandler implements RequestHandlerInterface
{
    public function __construct(private SourceApiFacadeInterface $sourceApiFacade) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organization = RequestContextAttributes::currentOrganization($request);
        if (! $organization instanceof OrganizationDto) {
            return $this->json([
                'error' => 'Organization not found',
            ], 404);
        }

        $authorizationId = RouteAttributes::uuid($request, 'authorizationId');
        if ('' === $authorizationId) {
            return $this->json([
                'error' => 'Authorization not found.',
            ], 404);
        }

        $result = $this->sourceApiFacade->checkAuthorizationDependencies($authorizationId, $organization->id);

        return $this->json($result);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function json(array $result, int $status = 200): JsonResponse
    {
        return new JsonResponse($result, $status);
    }
}
