<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Common\Registry\RegistryProtocolPermissions;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Infrastructure\Request\RegistryTokenIssueRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

use function sprintf;

#[Authenticated]
#[Can('tokens.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post('/organization/:organization/tokens', name: 'tokens.issue')]
#[MapRequest(body: RegistryTokenIssueRequest::class)]
#[ValidateMappedRequest(RegistryTokenIssueRequest::class)]
#[ValidationFailed(
    redirectTo: '/organization/{organization}/tokens',
    message: 'Invalid request data.',
    notify: ValidationNotify::None,
)]
final readonly class RegistryTokenIssueHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);

        /** @var RegistryTokenIssueRequest $form */
        $form = $request->getAttribute(RegistryTokenIssueRequest::class);

        $issuedRegistryAccessTokenDto = $this->identityApiFacade->issueRegistryAccessTokenInContext(
            $actorContext,
            $form->name,
            [RegistryProtocolPermissions::PACKAGES_READ_PRIVATE],
        );

        $this->inertiaNotificationMessenger->flash(
            $request,
            'success',
            sprintf(
                'New registry token for "%s": %s',
                $issuedRegistryAccessTokenDto->name,
                $issuedRegistryAccessTokenDto->plainTextToken,
            ),
        );

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/tokens');
    }
}
