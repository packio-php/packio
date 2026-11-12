<?php

declare(strict_types=1);

namespace Packio\Source\Infrastructure\Handler;

use Common\Logging\SafeExceptionContext;
use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Source\Api\Dto\ProviderAuthorizationRequestDto;
use Packio\Source\Api\SourceApiFacadeInterface;
use Packio\Source\Domain\Service\SourceAuthorizationStrategyResolver;
use Packio\Source\Infrastructure\Request\ProviderAuthorizationRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Rbac\Attribute\Can;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;
use Throwable;

#[Authenticated]
#[Can('sources.manage', [
    'organization' => RequestContextAttributes::CURRENT_ORGANIZATION,
    'membership'   => RequestContextAttributes::CURRENT_MEMBERSHIP,
])]
#[Post(
    '/organization/:organization/sources/authorizations',
    name: 'sources.authorizations.store',
)]
#[MapRequest(body: ProviderAuthorizationRequest::class)]
#[ValidateMappedRequest(ProviderAuthorizationRequest::class)]
#[ValidationFailed(
    redirectTo: '/organization/{organization}/sources',
    message: 'Invalid request data.',
    notify: ValidationNotify::Toast,
)]
final readonly class SourceAuthorizationStoreHandler implements RequestHandlerInterface
{
    public function __construct(
        private SourceApiFacadeInterface $sourceApiFacade,
        private SourceAuthorizationStrategyResolver $sourceAuthorizationStrategyResolver,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private ?LoggerInterface $logger = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationDto      = RequestContextAttributes::requireCurrentOrganization($request);
        $actorContext         = RequestContextAttributes::requireCurrentOrganizationActorContext($request);

        /** @var ProviderAuthorizationRequest $form */
        $form = $request->getAttribute(ProviderAuthorizationRequest::class);

        try {
            $this->sourceApiFacade->authorizeProviderInContext(
                $actorContext,
                new ProviderAuthorizationRequestDto(
                    provider: $form->provider,
                    organizationId: $organizationDto->id,
                    authStrategy: $this->sourceAuthorizationStrategyResolver->defaultFor($form->provider),
                    payload: [
                        'token' => $form->authorizationToken,
                        ...('gitlab_self_hosted' === $form->provider && '' !== $form->providerInstanceId
                            ? [
                                'provider_instance_id' => $form->providerInstanceId,
                            ]
                            : []),
                        ...('gitlab_self_hosted' === $form->provider && '' !== $form->instanceUrl
                            ? [
                                'base_url' => $form->instanceUrl,
                            ]
                            : []),
                        ...('generic_git' === $form->provider && '' !== $form->authScheme
                            ? [
                                'auth_scheme' => $form->authScheme,
                            ]
                            : []),
                    ],
                ),
            );

            $this->inertiaNotificationMessenger->toast($request, 'success', 'Provider authorization saved. Available resources were refreshed.');
        } catch (Throwable $exception) {
            $this->logger?->error('Provider authorization failed.', SafeExceptionContext::forRequest($exception, $request));
            $this->inertiaNotificationMessenger->toast($request, 'error', 'Provider authorization failed.');
        }

        return new RedirectResponse('/organization/' . $organizationDto->slug . '/sources');
    }
}
