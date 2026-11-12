<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Middleware\RequireExistingOrganizationMiddleware;
use Packio\Identity\Api\Dto\RegistryAccessTokenDto;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Organization\Api\Policy\OrganizationPolicy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Get;

use function array_map;

#[Authenticated]
#[Can('tokens.view', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Get('/organization/:organization/tokens', name: 'tokens.index', middleware: [RequireExistingOrganizationMiddleware::class])]
final readonly class RegistryTokensHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private OrganizationPolicy $organizationPolicy,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        $organizationDto = RequestContextAttributes::requireCurrentOrganization($request);
        $membership      = RequestContextAttributes::currentMembership($request);
        $canManageTokens = $this->organizationPolicy->canManage($organizationDto, $membership);

        $tokens = array_map(
            fn (RegistryAccessTokenDto $registryAccessTokenDto): array => $this->tokenPayload($registryAccessTokenDto, $canManageTokens),
            $this->identityApiFacade->registryAccessTokensForOrganization($organizationDto->id),
        );

        return $inertia->render('Tokens/Index', [
            'tokens'            => $tokens,
            'organization'      => [
                'id'   => $organizationDto->id,
                'slug' => $organizationDto->slug,
                'name' => $organizationDto->name,
            ],
            'can_manage_tokens' => $canManageTokens,
        ]);
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     permissions: list<string>,
     *     created_at: string,
     *     last_used_at: string,
     *     masked_token: string,
     *     plain_token: null|string
     * }
     */
    private function tokenPayload(RegistryAccessTokenDto $registryAccessTokenDto, bool $revealPlainToken): array
    {
        return [
            'id'           => $registryAccessTokenDto->id,
            'name'         => $registryAccessTokenDto->name,
            'permissions'  => $registryAccessTokenDto->permissions,
            'created_at'   => $registryAccessTokenDto->createdAt,
            'last_used_at' => $registryAccessTokenDto->lastUsedAt,
            'masked_token' => $registryAccessTokenDto->maskedToken,
            // Only owners may copy a stored token; members see the masked value.
            'plain_token'  => $revealPlainToken ? $registryAccessTokenDto->plainToken : null,
        ];
    }
}
