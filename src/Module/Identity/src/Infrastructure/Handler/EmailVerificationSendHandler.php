<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Session\SessionInterface;
use Packio\Application\Routing\Attribute\RateLimit;
use Packio\Application\Security\Session\PendingEmailVerificationSession;
use Packio\Identity\Api\EmailVerificationReasonType;
use Packio\Identity\Api\IdentityApiFacadeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sirix\Mezzio\Routing\Attributes\Attribute\Post;
use Throwable;

#[Post('/auth/verification/send', name: 'auth.verification.send')]
#[RateLimit(
    key: 'email_verification_send',
    by: ['ip'],
    response: 'json',
    message: 'Too many requests. Please try again later.',
)]
final readonly class EmailVerificationSendHandler implements RequestHandlerInterface
{
    public function __construct(
        private IdentityApiFacadeInterface $identityApiFacade,
        private PendingEmailVerificationSession $pendingEmailVerificationSession = new PendingEmailVerificationSession(),
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userUuid = $this->getUserUuidFromSession($request);
        if (null === $userUuid) {
            return $this->json([
                'error' => 'No pending verification',
            ]);
        }

        $session           = $request->getAttribute('session');
        $registrationDelay = $this->pendingEmailVerificationSession->registrationDelay($session);

        try {
            if (null === $registrationDelay || 0 === $registrationDelay) {
                $this->identityApiFacade->requestEmailVerification($userUuid, true, EmailVerificationReasonType::ManualResend);
            }
        } catch (Throwable) {
            if (null === $registrationDelay) {
                return $this->json([
                    'error' => 'Unable to send verification email right now. Please try again.',
                ]);
            }
        }

        if (0 === $registrationDelay) {
            $this->pendingEmailVerificationSession->markRegistrationDelivery($session);
        }

        return $this->json([
            'success' => true,
            'message' => 'Verification code sent.',
        ]);
    }

    private function getUserUuidFromSession(ServerRequestInterface $serverRequest): ?string
    {
        $session = $serverRequest->getAttribute('session');
        if (! $session instanceof SessionInterface) {
            return null;
        }

        return $this->pendingEmailVerificationSession->userUuid($session);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data, int $status = 200): ResponseInterface
    {
        return new JsonResponse($data, $status);
    }
}
