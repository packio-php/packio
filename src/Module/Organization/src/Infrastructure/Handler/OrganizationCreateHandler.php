<?php

declare(strict_types=1);

namespace Packio\Organization\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Inertia\ValidationRedirectState;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Application\Routing\Attribute\ValidationFailed;
use Packio\Application\Routing\Attribute\ValidationNotify;
use Packio\Organization\Api\Exception\OrganizationExceptionInterface;
use Packio\Organization\Api\OrganizationApiFacadeInterface;
use Packio\Organization\Infrastructure\Request\OrganizationCreateRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Authenticated]
#[Post('/organizations', name: 'organizations.create')]
#[MapRequest(body: OrganizationCreateRequest::class)]
#[ValidateMappedRequest(OrganizationCreateRequest::class)]
#[ValidationFailed(
    redirectTo: '/organizations',
    message: 'Invalid request data.',
    notify: ValidationNotify::None,
    oldInput: ['name'],
)]
final readonly class OrganizationCreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private OrganizationApiFacadeInterface $organizationApiFacade,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private ValidationRedirectState $validationRedirectState = new ValidationRedirectState()
    ) {}

    /**
     * @throws ReflectionException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var OrganizationCreateRequest $form */
        $form = $request->getAttribute(OrganizationCreateRequest::class);

        $userId = RequestContextAttributes::currentUserId($request) ?? '';

        try {
            $organization = $this->organizationApiFacade->registerOrganization($form->name, $userId);
        } catch (OrganizationExceptionInterface $exception) {
            $this->validationRedirectState->errors($request, [
                'name' => [$exception->userMessage()],
            ]);
            $this->validationRedirectState->old($request, [
                'name' => $form->name,
            ]);

            return new RedirectResponse('/organizations');
        }

        $this->inertiaNotificationMessenger->flash($request, 'success', 'Organization created successfully.');

        return new RedirectResponse('/organization/' . $organization->slug . '/packages');
    }
}
