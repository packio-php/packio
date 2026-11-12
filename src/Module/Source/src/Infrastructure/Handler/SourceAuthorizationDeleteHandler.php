<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Infrastructure\Http\RouteAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Infrastructure\Request\ProviderAuthorizationDeleteRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

use function sprintf;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post(
    '/organization/:organization/sources/authorizations/:authorizationId/delete',
    name: 'sources.authorizations.destroy',
)]
#[MapRequest(body: ProviderAuthorizationDeleteRequest::class)]
#[ValidateMappedRequest(ProviderAuthorizationDeleteRequest::class)]
final readonly class SourceAuthorizationDeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);
        $authorizationId      = RouteAttributes::requireString($request, 'authorizationId');

        /** @var ProviderAuthorizationDeleteRequest $form */
        $form               = $request->getAttribute(ProviderAuthorizationDeleteRequest::class);
        $newAuthorizationId = $form->newAuthorizationId;

        $result = $this->sourceApiFacade->reassignAndDeleteProviderAuthorizationInContext(
            $actorContext,
            $authorizationId,
            '' !== $newAuthorizationId ? $newAuthorizationId : null,
        );

        if ($result['success']) {
            $reassigned = $result['reassigned'] ?? 0;
            $message    = '' === $newAuthorizationId
                ? 'Provider authorization deleted.'
                : sprintf('Provider authorization deleted. %d source(s) reassigned.', $reassigned);
            $this->inertiaNotificationMessenger->toast($request, 'success', $message);
        } else {
            $this->inertiaNotificationMessenger->toast($request, 'error', $result['error'] ?? 'Failed to delete provider authorization.');
        }

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
    }
}
