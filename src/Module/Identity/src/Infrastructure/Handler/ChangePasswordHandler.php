<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Packio\Application\Inertia\InertiaNotificationMessenger;
use Packio\Application\Infrastructure\Http\RequestContextAttributes;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Routing\Attribute\ValidateMappedRequest;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Packio\Identity\Application\Query\IdentityQueryService;
use Packio\Identity\Infrastructure\Page\ProfileViewModelFactory;
use Packio\Identity\Infrastructure\Request\ChangePasswordRequest;
use Packio\Shared\Api\Dto\UserDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\Mezzio\Authentication\Attribute\Authenticated;
use Sirix\Mezzio\Authentication\Contract\AuthManagerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Sirix\Mezzio\Valinor\Attribute\MapRequest;

#[Post('/profile/password', name: 'profile.password.update')]
#[Authenticated]
#[RateLimit(
    key: 'password_change',
    by: ['user'],
    response: 'redirect',
    message: 'Too many requests. Please try again later.',
    redirectTo: '/profile',
)]
#[MapRequest(body: ChangePasswordRequest::class)]
#[ValidateMappedRequest(ChangePasswordRequest::class)]
final readonly class ChangePasswordHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private IdentityQueryService $identityQueryService,
        private InertiaNotificationMessenger $inertiaNotificationMessenger,
        private ProfileViewModelFactory $profileViewModelFactory,
        private AuthManagerInterface $authManager,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var InertiaInterface $inertia */
        $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

        /** @var ChangePasswordRequest $form */
        $form = $request->getAttribute(ChangePasswordRequest::class);

        $currentUser = RequestContextAttributes::currentUser($request);
        if (! $currentUser instanceof UserDto) {
            return new RedirectResponse('/auth/login');
        }

        $updateMethod = $currentUser->hasPassword ? 'changePassword' : 'setPassword';

        // If user has a password, verify current password
        if ('changePassword' === $updateMethod) {
            if (! $this->identityApiFacade->changePassword($currentUser->id, $form->currentPassword, $form->newPassword)) {
                $profileData = $this->buildProfileData($currentUser);
                $inertia->share('errors', [
                    'current_password' => ['Current password is incorrect.'],
                ]);

                return $inertia->render('Profile/Show', $profileData);
            }
        } elseif (! $this->identityApiFacade->setPassword($currentUser->id, $form->newPassword)) {
            // Set password for first time
            $profileData = $this->buildProfileData($currentUser);
            $inertia->share('errors', [
                'new_password' => ['Unable to set password. Please try again.'],
            ]);

            return $inertia->render('Profile/Show', $profileData);
        }

        $message = 'changePassword' === $updateMethod ? 'Password updated successfully.' : 'Password set successfully.';
        $this->inertiaNotificationMessenger->flash($request, 'success', $message);

        // Re-issue this session with the new fingerprint; other sessions keep the old one and are rejected.
        return $this->authManager->login($request, new RedirectResponse('/profile'), [
            'user_id'                 => $currentUser->id,
            'credentials_fingerprint' => $this->identityQueryService->credentialsFingerprint($currentUser->id),
        ]);
    }

    /** @return array<string, mixed> */
    private function buildProfileData(UserDto $userDto): array
    {
        return $this->profileViewModelFactory->create(
            $userDto,
            $this->identityQueryService->findOAuthAccountsByUserId($userDto->id),
            $this->identityApiFacade->apiAccessTokensForUser($userDto->id),
        );
    }
}
